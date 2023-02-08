<?php

namespace App;

use App\FunctionalityGroup;
use App\ExcerptVariable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Excerpt extends Model
{
	protected $table = 'excerpts';
	protected $guarded = ['id'];

	public $timestamps = false;

	// These boolean -> integer casts are needed for groupBy(), 
	// since associative array keys can't be boolean
	public $casts = [
		'positive' => 'integer'
	];

	protected static $exception_patterns = [
		'/this spell costs @mc@ less to cast\b/ui' => 1,	// Casting less is a positive effect (Might not need with scoring system)
		'/^\{T\}\: Add @mc@(?:,?(?: or)? @mc@)*\.$/u' => 1,
		'/^Delve$/u' => 1
	//	'/^(?:\{[^\}]+\})+$/u' => null, 	// Text only contains manacost and nothing else? Can't deduce anything from it
	];

	public function scopePositive($query) 
	{
		return $query->where('positive', 1);
	}

	public function scopeNegative($query) 
	{
		return $query->where('positive', 0);
	}

	public function groups()
	{
		return $this->belongsToMany(Functionality::class, 'excerpt_group', 'excerpt_id', 'group_id')->withPivot(['amount']);
	}

	public function variables()
	{
		return $this->hasMany(ExcerptVariable::class, 'excerpt_id');
	}

	public function inferiors()
	{
		return $this->belongsToMany(Excerpt::class, 'excerpt_comparisons', 'superior_excerpt_id', 'inferior_excerpt_id')->using(ExcerptComparison::class)->withPivot(['id', 'reliability_points']);
	}

	public function superiors()
	{
		return $this->belongsToMany(Excerpt::class, 'excerpt_comparisons', 'inferior_excerpt_id', 'superior_excerpt_id')->using(ExcerptComparison::class)->withPivot(['id', 'reliability_points']);
	}

	public static function cardToRawExcerpts(Card $card)
	{

		// • == \x{2022} (Used in cards with choices)
		// Split by lines and dotted sentences
		$raws = preg_split('/(\n|(?<=\.)\s*)/u', $card->substituted_rules, -1, PREG_SPLIT_NO_EMPTY);
		$excerpts = [];

		foreach ($raws as $raw) {

			// Further split dotless lines by commas. Those lines contain keyword lists.
			// Uppercase first letter for each excerpt
			if (substr($raw, -1) !== '.' && substr($raw, -1) !== '—')
				$excerpts = array_merge($excerpts, array_map('ucfirst', preg_split('/(,\s+)/u', $raw, -1, PREG_SPLIT_NO_EMPTY)));
			else
				array_push($excerpts, ltrim($raw, '• '));
		}

		return $excerpts;
	}

	public static function cardToExcerpts(Card $card, $parse_values = true)
	{
		$raws = collect(self::cardToRawExcerpts($card));

		return $raws->map(function($raw) use ($parse_values, $card) {
			$excerpt = new Excerpt([
				'text' => ExcerptVariable::substituteVariablesInRules($raw),
				'positive' => null,
				'positivity_points' => 0,
				'negativity_points' => 0
			]);
			$excerpt->setRelation('variables', ExcerptVariable::getVariablesFromRaw($raw, $parse_values));
			$excerpt->setRelation('groups', collect([$card->functionality]));
			return $excerpt;
		});
	}

	public static function compare_by_text($a, $b) { return $a->text <=> $b->text; }
	public static function compare_by_text_pluralized($a, $b) 
	{ 
		$ret = $a->text <=> $b->text; 
		return ($ret != 0 && Excerpt::matchWithPluralOrSingular($a, $b)) ? 0 : $ret;
	}

	public static function getNewExcerpts(Card $inferior, Card $superior, int $round = 1)
	{
		$excerpts = collect([]);

		$inferior_excerpts = collect(Excerpt::cardToExcerpts($inferior, false))->keyBy('text');
		$superior_excerpts = collect(Excerpt::cardToExcerpts($superior, false))->keyBy('text');

		$common_excerpts = $superior_excerpts->intersectByKeys($inferior_excerpts);
		foreach ($common_excerpts as $text => $excerpt) {

			/*
			// Card may have multiple of same excerpt, check if the other card has same amount
			if ($inferior_excerpts[$text]->count() != $superior_excerpts[$text]->count()) {
				$common_excerpts->forget($text);
				continue;
			}*/

			//foreach ($excerpts as $excerpt) {

				// Establish which number/manacost is better, higher or lower?
				foreach ($excerpt->variables as $variable) {
					$search = function($item, $key) use ($variable) { return $item->isSameVariable($variable); };

					$variable->establishLowOrHighBetterness(
						$superior_excerpts[$text]->variables->first($search), 
						$inferior_excerpts[$text]->variables->first($search)
					);
				}
			//}
		}
		$excerpts = $excerpts->merge($common_excerpts->values());

		$diff_superior_excerpts = $superior_excerpts->diffKeys($common_excerpts);
		$diff_inferior_excerpts = $inferior_excerpts->diffKeys($common_excerpts);

		if ($round > 1) {

			// Filter excerpts that have alredy been deemed positive or are superior to a inferiors excerpt
			$positives = $superior->functionality->excerpts->filter(function($e) use ($inferior) {
				return /*$e->positive === 1 || */$e->inferiors->pluck('id')->intersect($inferior->functionality->excerpts->pluck('id'))->isNotEmpty();
			})->keyBy('text');

			// Filter excerpts that have alredy been deemed negative or are inferior to a superiors excerpt
			$negatives = $inferior->functionality->excerpts->filter(function($e) use ($superior) {
				return /*$e->positive === 0 || */$e->superiors->pluck('id')->intersect($superior->functionality->excerpts->pluck('id'))->isNotEmpty();
			})->keyBy('text');

			$diff_superior_excerpts = $diff_superior_excerpts->diffKeys($positives);
			$diff_inferior_excerpts = $diff_inferior_excerpts->diffKeys($negatives);
		}

		$diff_superior_excerpts = $diff_superior_excerpts->values();
		$diff_inferior_excerpts = $diff_inferior_excerpts->values();

		$count_inferior = $diff_inferior_excerpts->count();
		$count_superior = $diff_superior_excerpts->count();

		// Nothing on inferior -> All of superior are positive effects
		if ($count_inferior == 0) {
			foreach ($diff_superior_excerpts as $excerpt) {
				$excerpt->positivity_points++;
				$excerpt->positive = 1;
			}
			$excerpts = $excerpts->merge($diff_superior_excerpts);
		}

		// Nothing on superior -> All of inferior are negative effects
		else if ($count_superior == 0) {
			foreach ($diff_inferior_excerpts as $excerpt) {
				$excerpt->negativity_points++;
				$excerpt->positive = 0;
			}
			$excerpts = $excerpts->merge($diff_inferior_excerpts);
		}

		// Exactly one on both -> The one superior is superior to the one inferior
		else if ($count_inferior == 1 && $count_superior == 1) {

			$superior_excerpt = $diff_superior_excerpts->first();
			$inferior_excerpt = $diff_inferior_excerpts->first();

			//$diff_inferior_excerpts->first()->setRelation('variablecomparisons', $variable_comparisons);

			$superior_excerpt->setRelation('inferiors', $diff_inferior_excerpts);
			$inferior_excerpt->setRelation('superiors', $diff_superior_excerpts);

			$excerpts = $excerpts->push($superior_excerpt)->push($inferior_excerpt);
		}

		// Rest of the excerpts don't provide meaningful data for decision making, so ignore them

		// Override some values
		foreach ($excerpts as $key => $e) {
			foreach (self::$exception_patterns as $pattern => $override_value) {
				if (preg_match($pattern, $e->text) == 1) {
					if ($override_value === null) {
						unset($excerpts[$key]);
						continue 2;
					}
					else {
						$e->positive = $override_value;
						$e->positivity_points = ($override_value === 1) ? 1 : 0;
						$e->negativity_points = ($override_value === 0) ? 1 : 0;
					}
				}
			}
		}

		// return
		return $excerpts;
	}

	public function sumPoints(Excerpt $other)
	{
		$this->positivity_points += $other->positivity_points;
		$this->negativity_points += $other->negativity_points;
		$this->positive = ($this->positivity_points === $this->negativity_points) ? null : (int)($this->positivity_points > $this->negativity_points);
	//	$delta = $this->positivity_points - $this->negativity_points;
	//	$this->positive = (abs($delta) > 2) ? ($delta > 0) : null;

		return $this;
	}

	/*
		Attempts to match excerpt texts by replacing some words with plural/singular vesion of the word
		Caller is assumed to have already verified the texts don't match

		Return true if the texts match, otherwise false
	 */
	public static function matchWithPluralOrSingular(Excerpt $a, Excerpt $b) {

		// No variables? Don't think there will be anything to pluralize/singularize
		if (!$a->variables->isEmpty() && !$b->variables->isEmpty())
			return false;

		$word_pattern = '/\w+/u';
		preg_match_all($word_pattern, $a->text, $words_a);
		preg_match_all($word_pattern, $b->text, $words_b);

		// Different amount of words? This is not going to work
		if (count($words_a[0]) != count($words_b[0]))
			return false;

		// We can compare case-sensitively, since the variable word is at the same spot in both.
		// diff_a and diff_b will have atleast one element
		$diff_a = array_diff($words_a[0], $words_b[0]);
		$diff_b = array_diff($words_b[0], $words_a[0]);

		foreach ($diff_a as $key => $value) {
			if (Str::singular($value) !== $diff_b[$key] && 
				Str::plural($value) !== $diff_b[$key])

				return false;
		}

		return true;
	}
}
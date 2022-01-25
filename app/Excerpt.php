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
		'/this spell costs @mc@ less to cast\b/ui' => true,	// Casting less is a positive effect (Might not need with scoring system)
	//	'/^(?:\{[^\}]+\})+$/u' => null, 	// Text only contains manacost and nothing else? Can't deduce anything from it
	];

	public function getTmpAttribute() 
	{
		return $this->tmp;
	}

	public function setTmpAttribute($value)
	{
		$this->attributes['tmp'] = $value;
	}

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
		return $this->belongsToMany(FunctionalityGroup::class, 'excerpt_group', 'excerpt_id', 'group_id')->withPivot(['amount']);
	}

	public function variables()
	{
		return $this->hasMany(ExcerptVariable::class, 'excerpt_id');
	}

	public function inferiors()
	{
		return $this->belongsToMany(Excerpt::class, 'excerpt_comparisons', 'superior_excerpt_id', 'inferior_excerpt_id')->withPivot(['reliability_points']);
	}

	public function superiors()
	{
		return $this->belongsToMany(Excerpt::class, 'excerpt_comparisons', 'inferior_excerpt_id', 'superior_excerpt_id')->withPivot(['reliability_points']);;
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

		return $raws->map(function($raw) use ($parse_values) {
			$excerpt = new Excerpt([
				'text' => ExcerptVariable::substituteVariablesInRules($raw),
				'positive' => null,
				'positivity_points' => 0,
				'negativity_points' => 0
			]);
			$excerpt->setRelation('variables', ExcerptVariable::getVariablesFromRaw($raw, $parse_values));
			return $excerpt;
		});
	}

	public static function compare_by_text($a, $b) { return $a->text <=> $b->text; }
	public static function compare_by_text_pluralized($a, $b) 
	{ 
		$ret = $a->text <=> $b->text; 
		return ($ret != 0 && Excerpt::matchWithPluralOrSingular($a, $b)) ? 0 : $ret;
	}

	public static function getNewExcerpts(Card $inferior, Card $superior)
	{
		$excerpts = collect([]);

		$inferior_excerpts = collect(Excerpt::cardToExcerpts($inferior, false))->keyBy('text');
		$superior_excerpts = collect(Excerpt::cardToExcerpts($superior, false))->keyBy('text');

		$common_excerpts = $superior_excerpts->intersectByKeys($inferior_excerpts);
		foreach ($common_excerpts as $text => $excerpt) {

			//foreach ($excerpts as $excerpt) {

				// Establish which number/manacost is better, higher or lower?
				foreach ($excerpt->variables as $variable) {
					$search = function($item, $key) use ($variable) { return $item->isSameVariable($variable); };

					$variable->establishLowOrHighBetterness(
						$superior_excerpts[$text]->variables->first($search), 
						$inferior_excerpts[$text]->variables->first($search)
					);
				}
		//	}
		}
		$excerpts = $excerpts->merge($common_excerpts->values());

		$diff_superior_excerpts = $superior_excerpts->diffKeys($common_excerpts)->values();
		$diff_inferior_excerpts = $inferior_excerpts->diffKeys($common_excerpts)->values();

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
			$diff_superior_excerpts->first()->setRelation('inferiors', $diff_inferior_excerpts);
			$diff_inferior_excerpts->first()->setRelation('superiors', $diff_superior_excerpts);

			$excerpts = $excerpts->merge($diff_superior_excerpts)->merge($diff_inferior_excerpts);
		}

		// Rest of the excerpts don't provide meaningful data for decision making, so ignore them
		else {
			
		}

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
					//	$e->positivity_points = ($override_value === true) ? 1 : 0;
					//	$e->negativity_points = ($override_value === false) ? 1 : 0;
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
		$this->positive = ($this->positivity_points == $this->negativity_points) ? null : ($this->positivity_points > $this->negativity_points);

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

	public function isBetterThan($excerpts) {
		return $this->inferiors->whereIn('id', $excerpts->pluck('id')->all())->count() > 0;
	}

	public function isWorseThan($excerpts) {
		return $this->superiors->whereIn('id', $excerpts->pluck('id')->all())->count() > 0;
	}
}
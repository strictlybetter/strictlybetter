<?php

namespace App;

use App\FunctionalityGroup;

use Illuminate\Database\Eloquent\Model;

class Excerpt extends Model
{
	protected $table = 'excerpts';
	protected $guarded = ['id'];

	public $timestamps = false;

	// These boolean -> integer casts are needed for groupBy(), 
	// since associative array keys can't be boolean
	public $casts = [
		'positive' => 'integer',
		'regex' => 'integer'
	];

	protected static $exception_patterns = [
		'/this spell costs (?:\{[^\}]+\})+ less to cast\b/ui' => true,	// Casting less is a positive effect (Might not need with scoring system)
		'/^(?:\{[^\}]+\})+$/u' => null, 	// Text only contains manacost and nothing else? Can't deduce anything from it
		'/\bSurge \{/u' => true 	// Surge may be evaluated as negative, since some spells are cheaper even without Surge
	];

	// We need to find patterns from escaped source
	protected static $variable_patterns = [
		// Digits
		'/\b\d+\b/u' => [				
			'escaped' => '/\b\d+\b/u',
			'replacement' => '(\d+)',
			'type' => 'digits'
		],
		// Manacost
		'/(?:\{[^\}]+\})+/u' => [			
			'escaped' => '/(?:\\\\\{[^\}]+\\\\\})+/u',
			'replacement' => '((?:\{[^\}]+\})+)',
			'type' => 'manacost'
		],
		
		// Number words
		'/\b(one|two|three|four|five|six|seven|eight|nine|ten|elven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty)\b/u' => [
			'escaped' => '/\b(one|two|three|four|five|six|seven|eight|nine|ten|elven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty)\b/u',
			'replacement' => '(one|two|three|four|five|six|seven|eight|nine|ten|elven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty)',
			'type' => 'numberword'
		]								
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
		return $this->belongsToMany(FunctionalityGroup::class, 'excerpt_group', 'excerpt_id', 'group_id');
	}

	public static function cardToRawExcerpts(Card $card)
	{
		return array_filter(array_map(function($value) {
			return trim($value, " \n\r\t\v\0.");
		}, preg_split('/(\n|(?<!\}),|\.(?!\"))/u', $card->substituted_rules, -1, PREG_SPLIT_NO_EMPTY)));
	}

	public static function cardToExcerpts(Card $card)
	{
		$raws = self::cardToRawExcerpts($card);
		$collection = [];

		foreach ($raws as $raw) {
			$collection[] = new Excerpt([
				'text' => $raw,
				'positive' => null,
				'regex' => 1
			]);
		}
		return collect($collection);
	}

	public static function compare_by_text($a, $b) { return $a->text <=> $b->text; }
	public static function compare_by_regex_text($a, $b) { return self::rawToRegex($a) <=> self::rawToRegex($b); }

	public static function getNewExcerptsV2(Card $inferior, Card $superior, $make_regex = true)
	{
		$excerpts = collect([]);
/*
		$superior_substitute = new Excerpt([
			'text' => self::substituteVariablesInRules($superior->substituted_rules),
			'positive' => 1,
			'regex' => 1
		]);
		$superior_substitute->toRegex();

		$inferior_substitute = new Excerpt([
			'text' => self::substituteVariablesInRules($inferior->substituted_rules),
			'positive' => 1,
			'regex' => 0
		]);*/


		if ($inferior->substituted_rules == "") {
			$excerpts->push(new Excerpt([
				'text' => $superior->substituted_rules,
				'positive' => 1,
				'regex' => 0
			]));
		}
			
		else if ($superior->substituted_rules == "") {

			$excerpts->push(new Excerpt([
				'text' => $inferior->substituted_rules,
				'positive' => 0,
				'regex' => 0
			]));
		}

		else {

			if (($pos = mb_strpos($superior->substituted_rules, $inferior->substituted_rules)) !== false) {

				$second_start = $pos + mb_strlen($inferior->substituted_rules);
				if ($pos > 0)
					$excerpts->push(new Excerpt([
						'text' => mb_substr($superior->substituted_rules, 0, $pos),
						'positive' => 1,
						'regex' => 0
					]));

				if ($second_start < mb_strlen($superior->substituted_rules))	// strlen() optimization
					$excerpts->push(new Excerpt([
						'text' => mb_substr($superior->substituted_rules, $second_start),
						'positive' => 1,
						'regex' => 0
					]));

				/*
				$excerpts->push(new Excerpt([
					'text' => substr_replace($superior->substituted_rules, "", $pos, strlen($inferior->substituted_rules)),
					'positive' => 1,
					'regex' => 0
				]));*/
			}

			if (($pos = mb_strpos($inferior->substituted_rules, $superior->substituted_rules)) !== false) {

				$second_start = $pos + mb_strlen($superior->substituted_rules);
				if ($pos > 0)
					$excerpts->push(new Excerpt([
						'text' => mb_substr($inferior->substituted_rules, 0, $pos),
						'positive' => 0,
						'regex' => 0
					]));

				if ($second_start < mb_strlen($inferior->substituted_rules))
					$excerpts->push(new Excerpt([
						'text' => mb_substr($inferior->substituted_rules, $second_start),
						'positive' => 0,
						'regex' => 0
					]));

				/*
				$excerpts->push(new Excerpt([
					'text' => substr_replace($inferior->substituted_rules, "", $pos, strlen($superior->substituted_rules)),
					'positive' => 0,
					'regex' => 0
				]));
				*/
			}
		}

		foreach ($excerpts as $key => $e) {

			//	$text = preg_replace('/\s*?(?:\{.+?\})+$/u', '', $text); 	// Remove manacosts of some keyword abilities
			//	$text = preg_replace('/^.*?:/um', ':', $text);			// Remove costs of abilities, but leave : to indicate ability

			$e->text = trim($e->text, " \n\r\t\v\0,.");
			if ($e->text == "") {
				unset($excerpts[$key]);
				continue;
			}

			foreach (self::$exception_patterns as $pattern => $override_value) {
				if (preg_match($pattern, $e->text) == 1) {
					if ($override_value === null) {
						unset($excerpts[$key]);
						continue 2;
					}
					else 
						$e->positive = $override_value;
				}
			}

			$e->positivity_points = $e->positive ? 1 : 0;
			$e->negativity_points = $e->positive ? 0 : 1;

			$e->regex = $e->hasRegex();

			if ($make_regex && $e->regex) {
				$e->toRegex();
			}
		}
		return $excerpts;
	}

	public static function getNewExcerpts(Card $inferior, Card $superior)
	{
		$excerpts = collect([]);


		$inferior_excerpts = self::cardToRawExcerpts($inferior);
		$superior_excerpts = self::cardToRawExcerpts($superior);

/*
		foreach ($inferior_excerpts as $excerpt) {
			ExcerptVariable::getVariablesFromText($excerpt);
		}
*/		

//		$intesecting_excerpts = array_uintersect($inferior_excerpts, $superior_excerpts, self::compare_by_regex_text);
		$diff_inferior_excerpts = array_udiff($inferior_excerpts, $superior_excerpts, [Excerpt::class, 'compare_by_regex_text']);
		$diff_superior_excerpts = array_udiff($superior_excerpts, $inferior_excerpts, [Excerpt::class, 'compare_by_regex_text']);

		if (count($diff_inferior_excerpts) == 0) {
			foreach ($diff_superior_excerpts as $raw) {
				$excerpts->push(new Excerpt([
					'text' => $raw,
					'positive' => 1,
					'regex' => 0,
					'negativity_points' => 0,
					'positivity_points' => 1
				]));
			}
		}

		if (count($diff_superior_excerpts) == 0) {
			foreach ($diff_inferior_excerpts as $raw) {
				$excerpts->push(new Excerpt([
					'text' => $raw,
					'positive' => 0,
					'regex' => 0,
					'negativity_points' => 1,
					'positivity_points' => 0
				]));
			}
		}

		foreach ($excerpts as $key => $e) {
			foreach (self::$exception_patterns as $pattern => $override_value) {
				if (preg_match($pattern, $e->text) == 1) {
					if ($override_value === null) {
						unset($excerpts[$key]);
						continue 2;
					}
					else 
						$e->positive = $override_value;
				}
			}
		}

		return $excerpts;
	}

	public function hasRegex()
	{
		foreach (self::$variable_patterns as $variable_pattern => $extra) {
			if (preg_match($variable_pattern, $this->text) == 1)
				return true;
		}
		return false;
	}

	public static function rawToRegex($text)
	{
		$pattern = preg_quote($text, '/');
		$pattern = self::substituteVariablesInRules($pattern, true);
		return '/' . $pattern . '/u';
	}

	public function toRegex()
	{
		$this->text = self::rawToRegex($this->text);
	}

	public static function substituteVariablesInRules($rules, $escaped = false)
	{
		foreach (self::$variable_patterns as $variable_pattern => $extra) {
			$rules = preg_replace($escaped ? $extra['escaped'] : $variable_pattern, $extra['replacement'], $rules);
		}
		return $rules;
	}
}
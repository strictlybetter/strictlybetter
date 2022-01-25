<?php

namespace App;

use App\Excerpt;
use App\Manacost;
use App\ExcerptVariableValue;

use Illuminate\Database\Eloquent\Model;


class DigitHandler {

	public static function parser($value) { return (int)$value; }
	public static function comparator(int $a, int $b) { return $a <=> $b; }
	public static function toArrayFn(int $digit) { return [$digit]; }
	public static function toValueFn(array $array) { return $array[0]; }
}

class ManacostHandler {

	public static function parser($string) { return Manacost::createFromManacostString($string); }
	public static function comparator(Manacost $a, Manacost $b) { return $a->compareCost($b, false); }
	public static function toArrayFn(Manacost $mc) { return $mc->toExcerptValueArray(); }
	public static function toValueFn(array $array) { return Manacost::createFromExcerptValueArray($array); }
}

class NumberWordHandler extends DigitHandler {

	public static function parser($word) { return self::$value_map[$word]; }	

	protected static $value_map = [
		'zero'      => 0,
		'no'		=> 0,
		'a'         => 1,
		'an'        => 1,
		'one'       => 1,
		'two'       => 2,
		'three'     => 3,
		'four'      => 4,
		'five'      => 5,
		'six'       => 6,
		'seven'     => 7,
		'eight'     => 8,
		'nine'      => 9,
		'ten'       => 10,
		'eleven'    => 11,
		'twelve'    => 12,
		'thirteen'  => 13,
		'fourteen'  => 14,
		'fifteen'   => 15,
		'sixteen'   => 16,
		'seventeen' => 17,
		'eighteen'  => 18,
		'nineteen'  => 19,
		'twenty'    => 20
	];
}


class ExcerptVariable extends Model
{
	protected $table = 'excerpt_variables';
	protected $guarded = ['id'];

	public $timestamps = false;

	// These boolean -> integer casts are needed for groupBy(), 
	// since associative array keys can't be boolean
	public $casts = [
		'more_is_better' => 'integer',
	];

	// Run-time value attribute setter/getter
	private $value = null;

	// We need to find patterns from escaped source
	protected static $variable_patterns = [

		// Manacost
		'manacost' => [			
			'pattern' => '/(?:\{[^\}TQ∞]+\})+/u',
			'placeholder' => '@mc@',
			'handler' => ManacostHandler::class
		],

		// Digits 
		'digits' => [			
			'pattern' => '/(?<!\{)\b\d+\b/u',
			'placeholder' => '@d@',
			'handler' => DigitHandler::class
		],
		
		// Number words
		'numberword' => [
			'pattern' => '/\b(?:zero|no|a|an|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty)\b/u',
			'placeholder' => '@nw@',
			'handler' => NumberWordHandler::class
		]								
	];


	// Used to find excerpts variables after an array intersect
	// Thus, we don't need to check for variable_id, just the finer details
	public function isSameVariable($other)
	{
		return $this->capture_id === $other->capture_id && $this->capture_type === $other->capture_type;
	}

	public function excerpt()
	{
		return $this->belongsTo(Excerpt::class);
	}

	public function values()
	{
		return $this->hasMany(ExcerptVariableValue::class, 'variable_id');
	}

	public static function getVariablesFromRaw($raw, $parse_values = false) 
	{

		$variables = collect([]);

		// Record variable costs
		foreach (self::$variable_patterns as $type => $data) {
			preg_match_all($data['pattern'], $raw, $matches, PREG_SET_ORDER);
			
			foreach ($matches as $capture_id => $match) {
				$var = new ExcerptVariable([
					'capture_type' => $type,
					'capture_id' => $capture_id,
					'more_is_better' => null,
					'points_for_less' => 0,
					'points_for_more' => 0
				]);

				// Set runtime value (not saved to DB like this)
				$var->value = $parse_values ? $data['handler']::parser($match[0]) : $match[0];

				$variables->push($var);
			}
		}
		return $variables;
	}

	public static function substituteVariablesInRules($rules)
	{
		foreach (self::$variable_patterns as $type => $data) {
			$rules = preg_replace($data['pattern'], $data['placeholder'], $rules);
		}
		return $rules;
	}

	private function handler() {
		return self::$variable_patterns[$this->capture_type]['handler'];
	}

	public function valueComparison($a, $b) 
	{

		//$comparator = self::$variable_patterns[$this->capture_type]['comparator'];
		$result = $this->more_is_better ? $this->handler()::comparator($a, $b) : $this->handler()::comparator($b, $a);

		// If we don't which is supposed to be better, only allow equivalency or fail
		return ($this->more_is_better === null && $result != 0) ? -1 : $result;	
	}

	public function valueComparisonDb(ExcerptVariableValue $a, ExcerptVariableValue $b) 
	{
		$val_a = $this->arrayToValue($a->value);
		$val_b = $this->arrayToValue($b->value);

		// $comparator = self::$variable_patterns[$this->capture_type]['comparator'];
		$result = $this->more_is_better ? $this->handler()::comparator($val_a, $val_b) : $this->handler()::comparator($val_b, $val_a);

		// If we don't which is supposed to be better, only allow equivalency or fail
		return ($this->more_is_better === null && $result != 0) ? -1 : $result;	
	}

	public function establishLowOrHighBetterness(ExcerptVariable $superior, ExcerptVariable $inferior) 
	{
		//$comparator = self::$variable_patterns[$this->capture_type]['comparator'];

		$result = $this->handler()::comparator($this->parseValue($superior->value), $this->parseValue($inferior->value));
		$this->more_is_better = ($result == 0) ? null : ($result > 0 ? 1 : 0);

		$this->points_for_more = ($this->more_is_better === 1) ? 1 : 0;
		$this->points_for_less = ($this->more_is_better === 0) ? 1 : 0;
	}

	public function sumPoints(ExcerptVariable $other)
	{
		$this->points_for_more += $other->points_for_more;
		$this->points_for_less += $other->points_for_less;
		$this->more_is_better = ($this->points_for_more == $this->points_for_less) ? null : ($this->points_for_more > $this->points_for_less);

		return $this;
	}

	public function parseValue($value = null) 
	{
		return $this->handler()::parser($value === null ? $this->value : $value);
	}

	public function valueToArray($value = null) 
	{
		return $this->handler()::toArrayFn($value === null ? $this->value : $value);
	}

	public function arrayToValue(array $array) 
	{
		return  $this->handler()::toValueFn($array); //self::$variable_patterns[$this->capture_type]['toValueFn']($value);
	}
}

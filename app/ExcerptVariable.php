<?php

namespace App;

use App\Excerpt;

use Illuminate\Database\Eloquent\Model;

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

	public function excerpt()
	{
		return $this->belongsTo(Excerpt::class);
	}

	public static function getVariablesFromText($text)

		$variables = [];

		// Record variable costs
		foreach (self::$variable_patterns as $variable_pattern => $extra) {
			preg_match_all($variable_pattern, $value, $matches, PREG_SET_ORDER);
			
			foreach ($matches as $match) {

				$variables[] = [
					'capture_type' => $extra['type']
					'capture_id' => $match[0],
					'value' => $match[1]
				]
			}

		}
		return $variables;
	}
}

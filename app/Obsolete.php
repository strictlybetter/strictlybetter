<?php

namespace App;

use App\Card;
use App\Suggestion;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Obsolete extends Pivot
{
	protected $table = 'obsoletes';
	protected $fillable = ['inferior_card_id', 'superior_card_id', 'upvotes', 'downvotes', 'labels'];
	/*'more_colors', 'more_colored_mana', 'supertypes_differ', 'types_differ', 'subtypes_differ', 'less_colors', 'strictly_better'*/

	//protected $label_columns = ['more_colors', 'more_colored_mana', 'supertypes_differ', 'types_differ', 'subtypes_differ', 'less_colors', 'strictly_better'];

	protected $appends = ['votesum'];

	protected $casts = ['labels' => 'array'];

	public function superior()
	{
		return $this->belongsTo(Card::class, 'superior_card_id');
	}

	public function inferior()
	{
		return $this->belongsTo(Card::class, 'inferior_card_id');
	}

	public function suggestions() 
	{
		return $this->hasMany(Suggestion::class, 'obsolete_id');
	}

	public function getVotesumAttribute()
	{
		return $this->upvotes - $this->downvotes;
	}

	public function getpresentLabelsAttribute()
	{
		$labels = [];
		foreach ($this->labels as $column) {
			if ($this->$column)
				$labels[] = $column;
		}
		return $labels;
	}
}
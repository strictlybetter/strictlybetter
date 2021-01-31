<?php

namespace App;

use App\Card;
use App\Vote;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Obsolete extends Pivot
{
	protected $table = 'obsoletes';
	/*'more_colors', 'more_colored_mana', 'supertypes_differ', 'types_differ', 'subtypes_differ', 'less_colors', 'strictly_better'*/

	public static $labellist = ['more_colors', 'more_colored_mana', 'supertypes_differ', 'types_differ', 'subtypes_differ', 'less_colors', 'strictly_better', 'downvoted'];
	protected $guarded = ['id'];

	protected $appends = ['votesum'];

	protected $casts = ['labels' => 'array'];
	protected $touches = ['inferior'];

	public function superior()
	{
		return $this->belongsTo(Card::class, 'superior_card_id');
	}

	public function inferior()
	{
		return $this->belongsTo(Card::class, 'inferior_card_id');
	}

	public function votes() 
	{
		return $this->hasMany(Vote::class, 'obsolete_id');
	}

	public function getVotesumAttribute()
	{
		return $this->upvotes - $this->downvotes;
	}
}
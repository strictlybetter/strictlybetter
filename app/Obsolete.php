<?php

namespace App;

use App\Card;
use App\Suggestion;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Obsolete extends Pivot
{
	protected $table = 'obsoletes';
	protected $fillable = ['inferior_card_id', 'superior_card_id', 'upvotes', 'downvotes'];

	protected $appends = ['votesum'];

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
}
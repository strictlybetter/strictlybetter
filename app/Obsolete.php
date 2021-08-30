<?php

namespace App;

use App\Card;
use App\Vote;
use App\Labeling;
use App\Functionality;

use Illuminate\Database\Eloquent\Model;

class Obsolete extends Model
{
	protected $table = 'obsoletes';
	protected $guarded = ['id'];

	protected $appends = ['votesum'];

/*
	public function superiors()
	{
		return $this->hasManyThrough(Card::class, Functionality::class, 'group_id', 'functionality_id', 'superior_functionality_group_id', 'id');
	}

	public function inferiors()
	{
		return $this->hasManyThrough(Card::class, Functionality::class, 'group_id', 'functionality_id', 'inferior_functionality_group_id', 'id');
	}
*/
//	return $this->belongsToMany(Card::class, 'labelings', 'superior_functionality_id', 'inferior_functionality_id', 'functionality_id', 'functionality_id');

	public function superiors()
	{
		return $this->belongsToMany(Card::class, 'functionalities', 'group_id', 'id', 'superior_functionality_group_id', 'functionality_id');
	}

	public function inferiors()
	{
		return $this->belongsToMany(Card::class, 'functionalities', 'group_id', 'id', 'inferior_functionality_group_id', 'functionality_id');
	}

	public function votes() 
	{
		return $this->hasMany(Vote::class, 'obsolete_id');
	}

	public function labelings() 
	{
		return $this->hasMany(Labeling::class, 'obsolete_id');
	}

	/*
	public function superiorLabelings()
	{
		return $this->belongsToMany(Labeling::class, 'cards', 'functionality_id', 'functionality_group_id', 'superior_functionality_group_id', 'superior_functionality_id');
	}

	public function inferiorLabelings()
	{
		return $this->belongsToMany(Labeling::class, 'cards', 'functionality_id', 'functionality_group_id', 'inferior_functionality_group_id', 'inferior_functionality_id');
	}*/

	public function getVotesumAttribute()
	{
		return $this->upvotes - $this->downvotes;
	}
}
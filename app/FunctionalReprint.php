<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Card;

class FunctionalReprint extends Model
{
	protected $table = 'functional_reprints';
	protected $fillable = ['typeline', 'manacost', 'power', 'toughness', 'loyalty', 'rules'];

    public function getFunctionReprintLineAttribute()
    {
    	return $this->typeline .'|'. $this->manacost .'|'. $this->power .'|'. $this->toughness .'|'. $this->loyalty .'|'. $this->rules;
    }

	public function cards()
	{
		return $this->hasMany(Card::class, 'functional_reprints_id');
	}
}
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
	protected $fillable = ['name', 'multiverse_id', 'legalities', 'price', 'manacost', 'cmc', 'supertypes', 'types', 'subtypes'];

	protected $casts = [
        'legalities' => 'array',
        'supertypes' => 'array',
        'types' => 'array',
        'subtypes' => 'array'
    ];

    protected $appends = ['imageUrl', 'gathererUrl'];

    public function inferiors() 
    {
    	return $this->belongsToMany(Card::class, 'obsoletes', 'superior_card_id', 'inferior_card_id')->withPivot(['upvotes', 'downvotes', 'id'])->withTimestamps();
    }

    public function superiors()
    {
    	return $this->belongsToMany(Card::class, 'obsoletes', 'inferior_card_id', 'superior_card_id')->withPivot(['upvotes', 'downvotes', 'id'])->withTimestamps();
    }

    public function getImageUrlAttribute()
    {
    	return 'https://gatherer.wizards.com/Handlers/Image.ashx?multiverseid=' . $this->multiverse_id . '&type=card';
    }

    public function getGathererUrlAttribute()
    {
    	return 'https://gatherer.wizards.com/Pages/Card/Details.aspx?multiverseid=' . $this->multiverse_id;
    }

    public function isSuperior(Card $other)
    {
    	// Types must match
    	if (count($this->supertypes) != count($other->supertypes) || array_diff($this->supertypes, $other->supertypes))
    		return false;

    	if (count($this->types) != count($other->types) || array_diff($this->types, $other->types)) {

    		// Instant vs Sorcery is an exception
    		if (!(in_array("Instant", $this->types) && in_array("Sorcery", $other->types)))
    			return false;
    	}

    	if (count($this->subtypes) != count($other->subtypes) || array_diff($this->subtypes, $other->subtypes))
    		return false;

    	if ($this->cmc > $other->cmc)
    		return false;

    	return true;
    }
}

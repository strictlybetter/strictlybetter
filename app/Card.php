<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\FunctionalReprint;

class Card extends Model
{
	protected $fillable = [
		'name', 
		'multiverse_id', 
		'legalities', 
		'price', 
		'manacost', 
		'cmc', 
		'supertypes', 
		'types', 
		'subtypes',
		'colors',
		'color_identity',
		'rules',
		'power',
		'toughness',
		'loyalty'
	];

	protected $casts = [
        'legalities' => 'array',
        'supertypes' => 'array',
        'types' => 'array',
        'subtypes' => 'array',
        'colors' => 'array',
        'color_identity' => 'array'
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

    public function functionalReprintGroup()
    {
    	return $this->belongsTo(FunctionalReprint::class, 'functional_reprints_id');
    }

    public function functionalReprints()
    {
    	return $this->hasMany(Card::class, 'functional_reprints_id', 'functional_reprints_id');
    }

    public function getImageUrlAttribute()
    {
    	return 'https://gatherer.wizards.com/Handlers/Image.ashx?multiverseid=' . $this->multiverse_id . '&type=card';
    }

    public function getGathererUrlAttribute()
    {
    	return 'https://gatherer.wizards.com/Pages/Card/Details.aspx?multiverseid=' . $this->multiverse_id;
    }

    public function getTypeLineAttribute()
    {
    	return trim(implode(" ", $this->supertypes) . " " . implode(" ", $this->types) . " - " . implode(" ", $this->subtypes), " -");
    }

    public function getFunctionalReprintLineAttribute()
    {
    	return $this->typeline .'|'. $this->manacost .'|'. $this->power .'|'. $this->toughness .'|'. $this->loyalty .'|'. $this->rules;
    }

    public function isSuperior(Card $other)
    {
    	// Must not be a duplicate
    	if ($this->id === $other->id || ($other->functional_reprints_id && $this->functional_reprints_id === $other->functional_reprints_id))
    		return false;

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

    	if (array_diff($this->colors, $other->colors))
    		return false;

    	if ($this->cmc > $other->cmc)
    		return false;

    	return true;
    }
}

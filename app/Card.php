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
		'loyalty',
		'scryfall_img',
		'scryfall_api',
		'scryfall_link'
	];

	protected $casts = [
        'legalities' => 'array',
        'supertypes' => 'array',
        'types' => 'array',
        'subtypes' => 'array',
        'colors' => 'array',
        'color_identity' => 'array',
        'manacost_sorted' => 'array'
    ];

    protected $colorManaCount = false;

    public static $formats = ['standard', 'modern', 'legacy', 'vintage', 'commander', 'pauper', 'penny', 'duel',  'future', 'frontier', 'oldschool'];

    protected $appends = ['imageUrl', 'gathererUrl'];

    public function inferiors() 
    {
    	return $this->belongsToMany(Card::class, 'obsoletes', 'superior_card_id', 'inferior_card_id')->using('App\Obsolete')->withPivot(['upvotes', 'downvotes', 'id', 'labels'])->withTimestamps();
    }

    public function superiors()
    {
    	return $this->belongsToMany(Card::class, 'obsoletes', 'inferior_card_id', 'superior_card_id')->using('App\Obsolete')->withPivot(['upvotes', 'downvotes', 'id', 'labels'])->withTimestamps();
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
    	return $this->multiverse_id ? ('https://gatherer.wizards.com/Handlers/Image.ashx?multiverseid=' . $this->multiverse_id . '&type=card') : $this->scryfall_img;
    }

    public function getGathererUrlAttribute()
    {
    	return $this->multiverse_id ? ('https://gatherer.wizards.com/Pages/Card/Details.aspx?multiverseid=' . $this->multiverse_id) : null;
    }

    public function getTypeLineAttribute()
    {
    	return trim(implode(" ", $this->supertypes) . " " . implode(" ", $this->types) . " - " . implode(" ", $this->subtypes), " -");
    }

	/*
		Should only be used to generate/populate substituted_rules field,
		in other cases use substituted_rules attribute
	*/
    public function getSubstituteRulesAttribute() 
    {
    	$name = preg_quote($this->name, '/');
    	$pattern = '/\b' . preg_replace('/\s+/u', '\s+', $name)  . '\b/u';

    	$substitute_rules = preg_replace($pattern, '@@@', $this->rules);

    	// Remove reminder text
    	return preg_replace('/^\(.*?\)\n/um', '', $substitute_rules);
    }

    public function getFunctionalReprintLineAttribute()
    {
    	return $this->typeline .'|'. $this->manacost .'|'. $this->power .'|'. $this->toughness .'|'. $this->loyalty .'|'. $this->substituted_rules;
    }

	/*
		Should only be used to generate/populate manacost_sorted field,
		in other cases use manacost_sorted attribute
	*/
    public function getColorManaCountsAttribute() 
    {
    	if ($this->colorManaCount)
    		return $this->colorManaCount;

    	if (!preg_match_all('/({[^\d]+?})/u', $this->manacost, $symbols))
			return false;

		$costs = [];
		foreach ($symbols[1] as $symbol) {
			if (!isset($costs[$symbol]))
				$costs[$symbol] = 1;
			else
				$costs[$symbol]++;
		}

		$this->colorManaCount = $costs;

		return $costs;
    }

    public function costsMoreColoredThan(Card $other, $may_cost_more_of_same = false)
    {
    	// If this costs nothing colored, it can't cost more
    	if ($this->manacost_sorted === false)
    		return false;

    	// If the other costs nothing colored, then this must cost more
    	if ($other->manacost_sorted === false)
    		return true;

    	$mana_left = $other->manacost_sorted;

    	$variable_costs = ['{X}','{Y}','{Z}'];

    	foreach ($variable_costs as $variable_cost)
    		unset($mana_left[$variable_cost]);

    	$anytype_left = $other->cmc - array_sum(array_values($mana_left));

    	foreach ($this->manacost_sorted as $symbol => $cost) {

    		if (in_array($symbol, $variable_costs))
    			continue;

			if (!isset($mana_left[$symbol])) {

				// Is it hybrid mana?
				$pos = mb_strpos($symbol, '/');
				if ($pos === false || $pos < 1)
					return true;

				// Translate Phyrexian mana to the base color
				// Phyrexian red > red
				if ($symbol[$pos + 1] === 'P')
					$symbol = '{'.$symbol[$pos-1].'}';

				// Translate multicolor / 2-generic/color to one of the left colors if any
				// multicolor / 2-generic/color > base color
				else {
					
					$symbol2 = '{'.$symbol[$pos-1].'}';
					$symbol = '{'.$symbol[$pos+1].'}';

					if (!isset($mana_left[$symbol]) || (
						($may_cost_more_of_same && $cost > ($anytype_left + $mana_left[$symbol])) || 
						(!$may_cost_more_of_same && $cost > $mana_left[$symbol])
					))
						$symbol = $symbol2;
				}

				if (!isset($mana_left[$symbol]))
					return true;

			}

			// Reduce usable mana for next iteration, unless we alredy ran out
			if ($cost > $mana_left[$symbol]) {
				if (!$may_cost_more_of_same || $cost > ($anytype_left + $mana_left[$symbol]))
					return true;
				else {
					$anytype_left -= ($cost - $mana_left[$symbol]);
					$mana_left[$symbol] = 0;
				}
			}
			else
				$mana_left[$symbol] -= $cost;
		}

		return false;
    }

    public function isNotWorseThan(Card $other)
    {
    	// Must not be a duplicate
    	if ($this->id === $other->id || ($other->functional_reprints_id && $this->functional_reprints_id === $other->functional_reprints_id))
    		return false;

    	// Types must match
    	if (count($this->supertypes) != count($other->supertypes) || array_diff($this->supertypes, $other->supertypes))
    		return false;

    	if (count($this->types) != count($other->types) || array_diff($this->types, $other->types)) {

    		// Instant vs Sorcery is an exception
    		// Creatures may have any other types
    		if (!(in_array("Instant", $this->types) && in_array("Sorcery", $other->types)) &&
    			!(in_array("Creature", $this->types) && in_array("Creature", $other->types)))
    			return false;
    	}

    	// If both have subtypes, atleast one of them has to be a common one
    	if (count($this->subtypes) > 0 && count($other->subtypes) > 0 && empty(array_intersect($this->subtypes, $other->subtypes)))
    		return false;

/*
    	if (count($this->subtypes) != count($other->subtypes) || array_diff($this->subtypes, $other->subtypes))
    		return false;*/
/*
    	if (array_diff($this->colors, $other->colors))
    		return false;*/

    	if ($this->cmc > $other->cmc)
    		return false;

    	if ($this->costsMoreColoredThan($other, true))
    		return false;
    	return true;
    }

    public function hasStats()
    {
    	return ($this->power !== null && $this->toughness !== null);
    }

    public function hasLoyalty() 
    {
    	return ($this->loyalty !== null);
    }
}

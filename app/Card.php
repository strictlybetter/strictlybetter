<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\FunctionalReprint;

class Card extends Model
{
	protected $guarded = ['id'];

	protected $casts = [
		'legalities' => 'array',
		'supertypes' => 'array',
		'types' => 'array',
		'subtypes' => 'array',
		'colors' => 'array',
		'color_identity' => 'array',
		'manacost_sorted' => 'array'
	];

	public static $all_supertypes = ["Basic", "Elite", "Host", "Legendary", "Ongoing", "Snow", "World"];
	public static $ignore_layouts = ["planar", "scheme", "token", "double_faced_token", "emblem", "art_series"];
	public static $ignore_types = ['Card // Card', 'Card', 'Plane', 'Scheme', 'Token', 'Emblem'];

	public static $functionality_attributes = [
		'typeline',
		'manacost',
		'power',
		'toughness',
		'loyalty',
		'substituted_rules'
	];

	public static $gui_attributes = [
		'cards.id',
		'name',
		'multiverse_id',
		'scryfall_img',
		'scryfall_link',
		'typeline',
		'functional_reprints_id',
		'main_card_id'
	];

	protected $with = ['cardFaces'];

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

	public function mainCard()
	{
		return $this->belongsTo(Card::class, 'main_card_id', 'id');
	}

	public function cardFaces()
	{
		return $this->hasMany(Card::class, 'main_card_id', 'id');
	}

	public function scopeGuiOnly($query, array $additional = [])
	{
		return $query->select(array_merge(Card::$gui_attributes, $additional));
	}

	public function getImageUrlAttribute()
	{
		if ($this->scryfall_img)
			return $this->scryfall_img;

		if ($this->multiverse_id)
		   return 'https://gatherer.wizards.com/Handlers/Image.ashx?multiverseid=' . $this->multiverse_id . '&type=card';
	
		return 'image/card-back.jpg';
	}

	public function getGathererImgAttribute()
	{
		return $this->multiverse_id ? ('https://gatherer.wizards.com/Handlers/Image.ashx?multiverseid=' . $this->multiverse_id . '&type=card') : null;
	}

	public function getGathererUrlAttribute()
	{
		return $this->multiverse_id ? ('https://gatherer.wizards.com/Pages/Card/Details.aspx?multiverseid=' . $this->multiverse_id) : null;
	}

	public function getFunctionalReprintLineAttribute()
	{
		$values = array_values($this->only(Card::$functionality_attributes));
		$line = implode("|", $values);

		foreach ($this->cardFaces as $face) {
			$values = array_values($face->only(Card::$functionality_attributes));
			$line = $line . '|' . implode("|", $values);
		}

		return $line;
	}

	/*
		Should only be used to generate/populate substituted_rules field,
		in other cases use substituted_rules attribute
	*/
	public function substituteRules() 
	{
		$name = preg_quote($this->name, '/');
		$pattern = '/(\b|^|\W)' . preg_replace('/\s+/u', '\s+', $name)  . '(\b|$|\W)/u';

		$substitute_rules = preg_replace($pattern, '\1@@@\2', $this->rules);

		// Remove reminder text
		return preg_replace('/^\(.*?\)\n/um', '', $substitute_rules);
	}

	/*
		Should only be used to generate/populate manacost_sorted field,
		in other cases use manacost_sorted attribute
	*/
	public function calculateColoredManaCosts() 
	{
		$costs = [];

		if (preg_match_all('/({[^\d]+?})/u', $this->manacost, $symbols)) {
			foreach ($symbols[1] as $symbol) {
				if (!isset($costs[$symbol]))
					$costs[$symbol] = 1;
				else
					$costs[$symbol]++;
			}
		}

		return $costs;
	}

	public function calculateCmcFromCost()
	{
		$cmc = null;
		
		if (preg_match('/{(\d+)}/u', $this->manacost, $matches))
			$cmc = $matches[1];

		$mana_counts = ($this->manacost_sorted !== null) ? $this->manacost_sorted : $this->calculateColoredManaCosts();

		if (empty($mana_counts))
			return $cmc;

		if ($cmc === null)
			$cmc = 0;

		foreach ($mana_counts as $symbol => $amount) {
			if (mb_strpos($symbol, '/2') === false)
				$cmc += $amount;
			else
				$cmc += ($amount * 2);
		}
		return $cmc;
	}

	public function costsMoreColoredThan(Card $other, $may_cost_more_of_same = false)
	{
		// If this costs nothing colored, it can't cost more
		if (empty($this->manacost_sorted))
			return false;

		// If the other costs nothing colored, then this must cost more
		if (empty($other->manacost_sorted))
			return true;

		$mana_left = $other->manacost_sorted;

		$variable_costs = ['{X}','{Y}','{Z}'];

		foreach ($variable_costs as $variable_cost)
			unset($mana_left[$variable_cost]);

		$cmc = $other->cmc ? $other->cmc : 0;
		$anytype_left = $cmc - array_sum(array_values($mana_left));

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

	/**
	 * Loosely determines if card is or can be equal or better than another card. 
	 * Does not compare rules text, power/toughness (as it can be modified by rules)
	 *
	 * This should not be used to find superior cards automatically, only to validate user suggestions.
	 *
	 * Currently compares:
	 * Matching supertypes, types (with few exceptions), subtypes (must have one in common).
	 * Manacost must be same or lower, some alternative costs are also considered when determining manacost
	 * 
	 * @param  Card - other card to compare to
	 * @return boolean - Costs more than the compared card
	 */
	public function isEqualOrBetterThan(Card $other)
	{
		// Must not be a duplicate
		if ($this->id === $other->id || ($other->functional_reprints_id && $this->functional_reprints_id === $other->functional_reprints_id))
			return false;

		// If this a multifaced card, check card faces against the other card
		// One of this cards faces must be better
		if (count($this->cardFaces) > 0) {

			if ($this->flip)
				return $this->cardFaces->first()->isEqualOrBetterThan($other) ? $this->cardFaces->first() : false;

			foreach ($this->cardFaces as $face) {
				if ($face->isEqualOrBetterThan($other))
					return $face;
			}
			return false;
		}

		// This card must not be worse than either of the other cards faces (in order to be truly superior)
		if (count($other->cardFaces) > 0) {

			foreach ($other->cardFaces as $other_face) {
				if (!$this->isEqualOrBetterThan($other_face))
					return false;
			}
			return true;
		}

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

		if ($other->cmc !== null && $this->cmc > $other->cmc) {

			// Check for a special case, where mana cost is less based on target
			$result = preg_match('/this spell costs (?:{.+?})+ less to cast if it targets (?:an? )?(.+?)\./ui', $this->substituted_rules, $match);
			if ($result != 1 || stripos($other->substituted_rules, "Target " . $match[1]) === false)
				return false;
		}

		if ($this->costsMoreColoredThan($other, true) && $this->alternativeCostsMoreColoredThan($other, true))
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

	/**
	 * Compares alternative costs for cards.
	 * 
	 * @param  Card - to compare to
	 * @param  boolean - can the original card cost more of same color. {W}{W} vs {1}{W} and still be better?
	 * @return boolean - Costs more than the compared card
	 */
	public function alternativeCostsMoreColoredThan(Card $other, $may_cost_more_of_same = false)
	{
		$alt = $this->getSortedAlternativeCosts();
		$alt2 = $other->getSortedAlternativeCosts();

		foreach ($alt as $keyword => $cost) {

			$dummy = new Card(); 
			$dummy->substituteAlternativeCost($cost);

			$compare_to = $other;

			if (isset($alt2[$keyword])) {
				$compare_to = new Card(); 
				$compare_to->substituteAlternativeCost($alt2[$keyword]);
			}

			if (($other->cmc === null || $dummy->cmc <= $compare_to->cmc) && !$dummy->costsMoreColoredThan($compare_to, $may_cost_more_of_same))
				return false;
		}
		return true;

	}

	/**
	 * Calculates new manacost for a card based on manacost in string format. 
	 * Quite heavy for batch processsing. If batch processing is needed, consider saving result to database during initial load (load-scryfall).
	 * 
	 * @param  String - Manacost in string format
	 * @return Card -  Altered card
	 */
	private function substituteAlternativeCost($manacost) 
	{
		$this->manacost = $manacost;
		$this->manacost_sorted = $this->calculateColoredManaCosts();
		$this->cmc = $this->calculateCmcFromCost();

		return $this;
	}

	/**
	 * Finds alternative manacost from card rules.
	 * Quite heavy for batch processsing. If batch processing is needed, consider saving result to database during initial load (load-scryfall).
	 * 
	 * @return Associateve array of alternative manacost keyword -> manacost
	 */
	public function getSortedAlternativeCosts() 
	{

		$alt_keywords = [
			// Keywords that change the circumstances of a spell's casting if an alternate cost is paid: 
		/*
			"Flashback", // — allows casting from the graveyard
			"Madness", // — allows casting as the spell is being discarded
			"Miracle", // — allows casting as the spell is being drawn
		*/
			// Keywords that make it easier to pay for the spell:
		/*
			"Assist", // — other players cay help pay for the normal mana cost
			"Convoke", // — tapping creatures can help pay for the normal mana cost
			"Emerge", // — allows the sacrifice of another creature to help pay for an alternate cost
			"Prowl", // — damage dealt by certain creatures enables an alternate cost
			"Spectacle", // — opponent's life loss enables an alternate cost
			"Surge", // — spells cast earlier in a turn enable an alternate cost
			"Suspend", // — an alternate cost is paid, but the spell resolves several turns later
		*/
			// Keywords that change or add to the spells effects when an alternate cost is paid:

		//	"Awaken", // — turns land in play into a creature
		//	"Bestow", // — a creature is cast as an enchantment
		//	"Dash", // — grants a creature haste, but returns it to hand at end of turn
		//	"Evoke", // — a creature is cast and immediately sacrificed, creating a sorcery-like effect
		//	"Morph", // — a creature is cast face down, but can be turned face up later
		//	"Megamorph", // — a creature is cast face down, but can be turned face up later
			"Overload", // — a spell affects all possible targets instead of just one
		];

		$words = implode("|", $alt_keywords);
		$pattern = '/\b('.$words.') ((?:{[^}]+})+)/u';

		preg_match_all($pattern, $this->substituted_rules, $matches, PREG_SET_ORDER);

		$costs = [];

		foreach ($matches as $val) {
			$costs[$val[0]] = $val[1];
		}
		return $costs;

	}
}

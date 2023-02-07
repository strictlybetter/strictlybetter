<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use \Staudenmeir\EloquentHasManyDeep\HasRelationships;
use App\Traits\CacheableAttribute;

use App\FunctionalReprint;
use App\Functionality;
use App\FunctionalityGroup;
use App\Labeling;
use App\Excerpt;
use App\Manacost;

class Card extends Model
{
	use HasRelationships;
	use CacheableAttribute;

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

	public static $functionality_group_attributes = [
		'manacost',
		'power',
		'toughness',
		'loyalty',
		'substituted_rules'
	];

	public static $functionality_group_exclusive_types = [
		'Land',
		'Creature',
		'Instant',
		'Sorcery'
	];

	public static $gui_attributes = [
		'cards.id',
		'name',
		'multiverse_id',
		'scryfall_img',
		'scryfall_link',
		'typeline',
		'functional_reprints_id',
		'main_card_id',
		'functionality_id'
	];

	protected $with = ['cardFaces'];

	protected $appends = ['imageUrl', 'gathererUrl'];

	public function inferiors() 
	{
		return $this->belongsToMany(Card::class, 'labelings', 'superior_functionality_id', 'inferior_functionality_id', 'functionality_id', 'functionality_id')
			->using(Labeling::class)->withPivot(['labels', 'id', 'obsolete_id'])->withTimestamps();
	}

	public function superiors()
	{
		return $this->belongsToMany(Card::class, 'labelings', 'inferior_functionality_id', 'superior_functionality_id', 'functionality_id', 'functionality_id')
			->using(Labeling::class)->withPivot(['labels', 'id', 'obsolete_id'])->withTimestamps();
	}

	public function getCategoryCountsAttribute()
	{
		return $this->cache('categoryCounts', function() {
			$categories = [
				'superiors' => ['count' => $this->superiors->count(), 'title' => 'superiors'],
			//	'similars' => ['count' => $this->relationLoaded('inferiors') ? $this->similars->count() : 0, 'title' => 'similar cards'],
				'typevariants' => ['count' => $this->relationLoaded('functionality') ? $this->functionality->typevariantcards->count() : 0, 'title' => 'type variants'],
				'inferiors' => ['count' => $this->relationLoaded('inferiors') ? $this->inferiors->count() : 0, 'title' => 'inferiors']
			];

			foreach ($categories as $key => $info) {
				if ($info['count'] <= 0)
					unset($categories[$key]);
			}
			$this->cached_attributes['categoryCounts'] = $categories;
			return $categories;
		});
	}

	public function functionality()
	{
		return $this->belongsTo(Functionality::class);
	}
/*
	public function similiars()
	{
		return $this->hasManyDeepFromRelations(
			$this->functionality(),
            (new Functionality)->similiarcards()
		);
	}*/

	/*	
	public function inferiorLabeling()
	{
		return $this->hasOne(Labeling::class, 'inferior_functionality_id', 'functionality_id');
	}

	public function superiorLabeling()
	{
		return $this->hasOne(Labeling::class, 'superior_functionality_id', 'functionality_id');
	}	
	*/

	public function functionalReprints()
	{
		return $this->hasMany(Card::class, 'functionality_id', 'functionality_id');
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

	public function scopeRelatedGuiOnly($query, array $additional = [])
	{
		return $query->guiOnly(array_merge(['obsolete_id', 'upvotes', 'downvotes'], $additional))
				->leftJoin((new Obsolete)->getTable(), 'labelings.obsolete_id', '=', 'obsoletes.id');
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

	public function getFunctionalityLineAttribute()
	{
		$values = array_values($this->only(Card::$functionality_attributes));
		$line = implode("|", $values);

		foreach ($this->cardFaces as $face) {
			$line = $line . '|' . $face->functionality_line;
		}

		return $line;
	}

	public function getFunctionalityGroupLineAttribute()
	{
		$values = array_values($this->only(Card::$functionality_group_attributes));

		foreach (Card::$functionality_group_exclusive_types as $type) {
			if (in_array($type, $this->types))
				$values[] = $type;
		}

		$line = implode("|", $values);

		foreach ($this->cardFaces as $face) {
			$line = $line . '|' . $face->functionality_group_line;
		}

		return $line;
	}
	/*
	public function findCardByFunctionality()
	{
		$face = $this;

		$functionality_rules = function($q) use ($face) {
			$q = $q->where($face->only(Card::$functionality_attributes))
			->whereNotNull('functionality_id')
			->whereNull('main_card_id');
		};

		// Try finding a card with identical characteristics and a set functionality_id
		$q = Card::with(['functionality.group'])//->select(['cards.id', 'functionality_id'])
			->where($functionality_rules);

		foreach ($this->cardFaces as $cardface) {
			$face = $cardface;
			$q = $q->whereHas('cardFaces', function($q) use ($functionality_rules) {
				$q->where($functionality_rules);
			});
		}

		return $q->orderBy('id')->first();
	}

	public function findCardByFunctionalityGroup()
	{
		$face = $this;

		$group_rules = function($q) use ($face) {
			$q = $q->where($face->only(Card::$functionality_group_attributes))
			->whereNotNull('functionality_id')
			->whereNull('main_card_id');

			foreach (Card::$functionality_group_exclusive_types as $type) {
				if (in_array($type, $face->types))
					$q = $q->whereJsonContains('types', $type);
				else
					$q = $q->whereJsonDoesntContain('types', $type);
			}
		};

		// Try finding a card with similar characteristics and a set functionality_id (for group_id)
		$q = Card::with(['functionality.group'])->where($group_rules);

		foreach ($this->cardFaces as $cardface) {
			$face = $cardface;
			$q = $q->whereHas('cardFaces', function($q) use ($group_rules) {
				$q->where($group_rules);
			});
		}

		return $q->orderBy('id')->first();
	}
	*/

	public function linkToFunctionality()
	{
		// Don't link individual card faces
		if ($this->main_card_id)
			return null;

		$migrate_from_orphan_functionality = $this->functionality_id ? $this->functionality : null;

		$algo = config('hashing.functionality_algorithm');
		$new_group = FunctionalityGroup::firstOrCreate(['hash' => hash($algo, $this->functionality_group_line)]);
		$new_functionality = Functionality::with('group')->firstOrCreate(['hash' => hash($algo, $this->functionality_line)], ['group_id' => $new_group->id]);

		if ($this->functionality_id != $new_functionality->id) {
			$this->functionality()->associate($new_functionality);
			$this->save();
		}

		$cards_left_in_functionality = true;
		$functionalities_left_in_group = true;

		// Move better-worse relations and voting data to new functionality if leaving orhpans
		if ($migrate_from_orphan_functionality) {

			$cards_left_in_functionality = ($migrate_from_orphan_functionality->id !== $new_functionality->id) ? ($migrate_from_orphan_functionality->cards()->count() > 0) : true;
			$functionalities_left_in_group = ($migrate_from_orphan_functionality->group_id !== $new_group->id) ? ($migrate_from_orphan_functionality->group->functionalities()->count() > 1) : true;

			if (!$cards_left_in_functionality || !$functionalities_left_in_group) {

				$migrate_from_orphan_functionality->migrateTo($new_functionality, $new_group);

				if (!$cards_left_in_functionality)
					$migrate_from_orphan_functionality->delete();
			}
		}

		// Change group_id only after migrating associated entities, 
		// because changing it will cause cascade update, which might run in to duplicates in unique columns in obsoletes table.
		if ($new_functionality->group_id !== $new_group->id) {
			$new_functionality->group_id = $new_group->id;
			$new_functionality->save();
		}

		if (!$functionalities_left_in_group)
			$migrate_from_orphan_functionality->group->delete();

		/*
		// TODO: Create labelings for our new relation
		if (!$new_group->wasRecentlyCreated && ($new_functionality->wasRecentlyCreated || $new_functionality->group_id != $new_group->id) ) {

			$superiors = Obsolete::with(['labelings'])->where('superior_functionality_group_id', $new_group->id);
			$inferiors = Obsolete::with(['labelings'])->where('inferior_functionality_group_id', $new_group->id);
			foreach ($superiors as $superior) {
				create_labeling()
			}

			foreach ($inferiors as $inferior) {
				create_labeling()
			}
		}
		*/

		return $this->functionality_id;
	}

	/*
		Should only be used to generate/populate substituted_rules field,
		in other cases use substituted_rules attribute

		TODO: Think about adding implicit rules here. Such as:

		Instant => Flash
		Land => Play one per turn
		Legendary => Sacrifice all but one duplicate

		This way machine learning could learn new things from rules. 
		Also grouping cards by functionality group could be easier, sicne we wouldn't need to care about types anymore.
	*/
	public function substituteRules() 
	{
		$name = preg_quote($this->name, '/');
		$pattern = '/(\b|^|\W)' . preg_replace('/\s+/u', '\s+', $name)  . '(\b|$|\W)/u';

		$substitute_rules = preg_replace($pattern, '\1@@@\2', $this->rules);	// Replace own name with @@@
		$substitute_rules = preg_replace('/;/u', ',', $substitute_rules);		// Some older cards separate keywords with ; replace to ,

		// Lands have their tap ability in parenthesis, remove the parenthesis
		$substitute_rules = preg_replace('/\(([^\)]*{T}: Add [^\)]*\.\"?)\)/u', '\1', $substitute_rules);

		// Remove all reminder texts
		$substitute_rules = preg_replace('/\s*?\([^\)]*\)/u', '', $substitute_rules);

		// Remove empty lines
		$substitute_rules = preg_replace('/^\n/um', '', $substitute_rules);
		return $substitute_rules;
	}

	public function compareCost(Card $other, $may_cost_more_of_same = false, $consider_alternatives = false, $alternative_base_cost_only = false)
	{

		if ($consider_alternatives) {
			$comparison = $this->compareAlternativeCosts($other, $may_cost_more_of_same, $alternative_base_cost_only);
			if ($comparison < 0)
				return $comparison;
		}

		$comparison = Manacost::createFromCard($this)->compareCost(Manacost::createFromCard($other), $may_cost_more_of_same);
		if ($consider_alternatives && $comparison > 0) {

			// Check for a special case, where mana cost is less based on target
			$result = preg_match('/this spell costs ((?:\{[^\}]+\})+) less to cast if it targets (?:an? )?(.+?)\./ui', $this->substituted_rules, $match);
			if ($result == 1 && stripos($other->substituted_rules, "Target " . $match[2]) !== false) {
				$reducted = Manacost::createFromCard($this)->substract(Manacost::createFromManacostString($match[1]), false);
				return $reducted->compareCost(Manacost::createFromCard($other), $may_cost_more_of_same);
			}
		}

		return $comparison;
	}

	public function compareColoredCost(Card $other, $may_cost_more_of_same = false)
	{
		return Manacost::createFromCard($this)->compareColoredCost(Manacost::createFromCard($other), $may_cost_more_of_same);
	}

	public static function findComparedFaces(&$superior, &$inferior) {

		// If inferior or superior is a multifaced card, 
		// search for the actual face that is better
		if (count($superior->cardFaces) == 0 && count($inferior->cardFaces) == 0)
			return true;

		foreach ($superior->cardFaces as $sup) {

			foreach($inferior->cardFaces as $inf) {
				if ($sup->isEqualOrBetterThan($inf)) {
					$superior = $sup;
					$inferior = $inf;
					return true;
				}
			}

			if ($sup->isEqualOrBetterThan($inferior)) {
				$superior = $sup;
				return true;
			}
		}
		
		foreach($inferior->cardFaces as $inf) {
			if ($superior->isEqualOrBetterThan($inf)) {
				$inferior = $inf;
				return true;
			}
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
	 * @return boolean/string - if $with_errors == true, returns true or a language string describing the error 
	 */
	public function isEqualOrBetterThan(Card $other, $with_errors = false)
	{
		// Must not be a duplicate
		if ($this->id === $other->id || ($this->main_card_id === null && $other->main_card_id === null && $this->functionality->id === $other->functionality->id))
			return $with_errors ? 'duplicate' : false;

		if ($this->main_card_id === null && $other->main_card_id === null && $this->functionality->group_id === $other->functionality->group_id)
			return $with_errors ? 'typevariant' : false;

		// If this a multifaced card, check card faces against the other card
		// One of this cards faces must be better
		if (count($this->cardFaces) > 0) {

			if ($this->flip) {
				return $this->cardFaces->first()->isEqualOrBetterThan($other, $with_errors);
			}

			$result = '';
			foreach ($this->cardFaces as $face) {
				$result = $face->isEqualOrBetterThan($other, $with_errors);
				if ($result === true)
					return true;
			}
			return $with_errors ? $result : false;
		}

		// This card must not be worse than either of the other cards faces (in order to be truly superior)
		if (count($other->cardFaces) > 0) {

			foreach ($other->cardFaces as $other_face) {
				$result = $this->isEqualOrBetterThan($other_face, $with_errors);
				if ($result !== true)
					return $result;
			}
			return true;
		}

		// This card must not be slower than the other card
		if ((in_array("Instant", $other->types) || preg_match('/\b(Flash|\w*Cycling|Channel)\b/i', $other->substituted_rules)) &&
			!(in_array("Instant", $this->types) || preg_match('/\b(Flash|\w*Cycling|Channel)\b/i', $this->substituted_rules)))
			return $with_errors ? 'not-instant' : false;


		// This card must not be a permanent, if the other is a non-permenent...
		$non_permanents = ["Sorcery", "Instant"];
		if (array_intersect($non_permanents, $other->types) && !array_intersect($non_permanents, $this->types)) {
			
			// ... Unless this permanent does it's thing when entering battlefield 
			// or can self sacrifice for the effect
			// or triggers when cycled
			if (!preg_match('/\bWhen (?!another)[^\.]* enters the battlefield/', $this->substituted_rules) &&
				!preg_match('/\bSacrifice @@@:/', $this->substituted_rules) &&
				!preg_match('/\bWhen(?:ever)? you cycle @@@/', $this->substituted_rules) &&
				!preg_match('/\b\w+cycling\b/', $this->substituted_rules) &&
				!preg_match('/\bChannel\b/', $this->substituted_rules))
				return $with_errors ? 'not-immediate' : false;
		}

		// This card must not cost more mana, but may cost more of the existing colors.
		if ($this->compareCost($other, true, true, true) > 0)
			return $with_errors ? 'costs-more' : false;

		return true;
	}

	public function hasStats()
	{
		return ($this->power !== null || $this->toughness !== null);
	}

	public function hasLoyalty() 
	{
		return ($this->loyalty !== null);
	}

	public function isBetterByRuleAnalysisThan(Card $other)
	{
		$superior_excerpts_org = $this->functionality->excerpts->keyBy('id');
		$inferior_excerpts_org = $other->functionality->excerpts->keyBy('id');

		$common_excerpts = $superior_excerpts_org->intersectByKeys($inferior_excerpts_org);
		$has_superior_variable_values = false;

		foreach ($common_excerpts as $key => $excerpt) {
			$compare_result = $this->compareCommonExcerptValues($excerpt, $other);
			if ($compare_result < 0)
				return false;
			$has_superior_variable_values = $has_superior_variable_values || ($compare_result > 0);
		}

		$superior_excerpts = $superior_excerpts_org->diffKeys($common_excerpts)->values();
		$inferior_excerpts = $inferior_excerpts_org->diffKeys($common_excerpts)->values();

		// If no differing excerpts are present, use presence of superior variable values to determine betterness
		if ($superior_excerpts->count() == 0 && $inferior_excerpts->count() == 0)
			return $has_superior_variable_values;

		// Remaining superior excerpts must all be positive or better than an inferior excerpt
		foreach ($superior_excerpts as $excerpt) {

			$comparee = $excerpt->inferiors->whereIn('id', $inferior_excerpts->pluck('id'))->first();
			if ($comparee) {
				if ($this->crossCompareExcerptValues($excerpt, $other, $comparee) < 0)
					return false;
			}
			else if ($excerpt->positive !== 1)
				return false;
		}

		// Remaining inferior excerpts must all be negative or worse than a superior excerpt
		foreach ($inferior_excerpts as $excerpt) {

			$comparee = $excerpt->superiors->whereIn('id', $superior_excerpts->pluck('id'))->first();
			if ($comparee) {
				if ($this->crossCompareExcerptValues($excerpt, $other, $comparee) < 0)
					return false;
			}
			else if ($excerpt->positive !== 0)
				return false;
		}

		return true;
	}

	private function crossCompareExcerptValues(Excerpt $excerpt, Card $other, Excerpt $other_excerpt) {

		$has_superior_variable_values = 0;

		foreach ($excerpt->variables as $variable) {
			foreach ($other_excerpt->variables as $other_variable) {

				if ($variable->isSameType($other_variable)) {

					$superior_value = $this->functionality->variablevalues->first(function($item, $key) use ($variable) { return $item->variable_id === $variable->id; });
					$inferior_value = $other->functionality->variablevalues->first(function($item, $key) use ($other_variable) { return $item->variable_id === $other_variable->id; });

					$result = $variable->valueComparisonDb(
						$superior_value, 
						$inferior_value
					);

					if ($result < 0 || $result > 1)
						return -1;

					else if ($result == 1)
						$has_superior_variable_values = 1;
				}
			}
		}
		return $has_superior_variable_values;
	}

	private function compareCommonExcerptValues(Excerpt $excerpt, Card $other) {

		$has_superior_variable_values = 0;
		foreach ($excerpt->variables as $variable) {

			$search = function($item, $key) use ($variable) {
				return $item->variable_id === $variable->id;
			};

			$result = $variable->valueComparisonDb(
				$this->functionality->variablevalues->first($search), 
				$other->functionality->variablevalues->first($search)
			);

			if ($result < 0 || $result > 1)
				return -1;

			else if ($result == 1)
				$has_superior_variable_values = 1;
		}
	

		return $has_superior_variable_values;
	}

	/**
	 * Compares alternative costs for cards.
	 * 
	 * @param  Card - to compare to
	 * @param  boolean - can the original card cost more of same color. {W}{W} vs {1}{W} and still be better?
	 * @return boolean - Costs more than the compared card
	 */
	public function compareAlternativeCosts(Card $other, $may_cost_more_of_same = false, $base_cost_only = false)
	{
		$alt = $this->getAlternativeCosts($base_cost_only);
		$alt2 = $other->getAlternativeCosts($base_cost_only);

		foreach ($alt as $keyword => $cost) {

			$compare_to = $alt2[$keyword] ?? Manacost::createFromCard($other);

			$result = $cost->compareCost($compare_to, $may_cost_more_of_same);
			if ($result < 0)
				return $result;
		}
		return 1;

	}

	/**
	 * Finds alternative manacost from card rules.
	 * Quite heavy for batch processsing. If batch processing is needed, consider saving result to database during initial load (load-scryfall).
	 * 
	 * @return Associateve array of alternative manacost keyword -> manacost
	 */
	public function getAlternativeCosts($base_cost_only = false) 
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

			"Awaken", // — turns land in play into a creature
			"Bestow", // — a creature is cast as an enchantment
			"Dash", // — grants a creature haste, but returns it to hand at end of turn
			"Evoke", // — a creature is cast and immediately sacrificed, creating a sorcery-like effect
			"Morph", // — a creature is cast face down, but can be turned face up later
			"Megamorph", // — a creature is cast face down, but can be turned face up later
			"Overload", // — a spell affects all possible targets instead of just one

			"Cycling",
			"Channel"
		/*
			"Foretell",
		*/
		];

		// Spells with these keywords cost additional colorless mana to get the full effect
		$alt_cost_keywords = [
			'Morph' => '{3}',
			'Megamorph' => '{3}'
		];

		$words = implode("|", $alt_keywords);
		$pattern = '/\b('.$words.') (?:\d*— ?)?((?:\{[^\}]+\})+)/u';

		preg_match_all($pattern, $this->substituted_rules, $matches, PREG_SET_ORDER);

		$costs = [];

		foreach ($matches as $val) {

			if ($base_cost_only)
				$costs[$val[1]] = isset($alt_cost_keywords[$val[1]]) ? Manacost::createFromManacostString($alt_cost_keywords[$val[1]]) : Manacost::createFromManacostString($val[2]);
			else	
				$costs[$val[1]] = Manacost::createFromManacostString($val[2])->add(isset($alt_cost_keywords[$val[1]]) ? Manacost::createFromManacostString($alt_cost_keywords[$val[1]]) : null, false);
		}
		return $costs;

	}
}

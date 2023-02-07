<?php

function escapeLike(string $value, string $char = '\\')
{
	return str_replace(
		[$char, '%', '_'],
		[$char.$char, $char.'%', $char.'_'],
		$value
	);
}

function make_select_options(array $arr, $lang = null)
{
	$options = [];
	foreach ($arr as $option) {
		$options[$option] = $lang ? Lang::get('card.' . $lang  . '.' . $option) : ucfirst(str_replace('_', ' ', $option));
	}
	return $options;
}

function get_tribes()
{
	return App\Cardtype::pluck('key')->toArray();
}

function make_tribe_list($append_default = true)
{
	$tribes = get_tribes();
	asort($tribes);

	$tribelist = $append_default ? ['' => 'Any Tribe'] : [];
	$tribelist = array_merge($tribelist, make_select_options($tribes));

	return $tribelist;
}

function get_formats()
{
	$samplecard = App\Card::whereNull('main_card_id')->first();
	return $samplecard ? array_keys($samplecard->legalities) : [];
}

function make_format_list($append_default = true)
{
	// Make list of supported formats
	$formats = get_formats();
	asort($formats);

	$formatlist = $append_default ? ['' => 'Any Format'] : [];
	$formatlist = array_merge($formatlist, make_select_options($formats));

	return $formatlist;
}

function migrate_obsoletes(App\Card $from, App\Card $to)
{
	$from->load(['inferiors', 'superiors']);

	$existing_inferiors = $to->inferiors->pluck('id')->toArray();
	$existing_superiors = $to->superiors->pluck('id')->toArray();

	// Migrate inferiors
	foreach ($from->inferiors as $inferior) {
		if (in_array($inferior->id, $existing_inferiors)) {

			$to_obsolete = App\Obsolete::with(['votes'])->where('superior_card_id', $to->id)->where('inferior_card_id', $inferior->id)->first();
			$from_obsolete = App\Obsolete::with(['votes'])->where('superior_card_id', $from->id)->where('inferior_card_id', $inferior->id)->first();
			foreach ($from_obsolete->votes as $vote) {

				// Same IP already exists, just delete the older vote
				if (in_array($vote->ip, $to_obsolete->votes->pluck('ip')->toArray()))
					$vote->delete();

				// Redirect the vote to other obsolete relation
				else {
					$vote->obsolete_id = $to_obsolete->id;
					if ($vote->upvote)
						$to_obsolete->upvotes++;
					else
						$to_obsolete->downvotes++;
					$vote->save();
				}
			}
			$to_obsolete->save();
			$from_obsolete->delete();
		}

		// Move inferior obsolete relation to other card
		else {
			App\Obsolete::where('superior_card_id', $from->id)->where('inferior_card_id', $inferior->id)->update(['superior_card_id' => $to->id]);
		}
	}

	// Migrate superiors
	foreach ($from->superiors as $superior) {
		if (in_array($superior->id, $existing_superiors)) {

			$to_obsolete = App\Obsolete::with(['votes'])->where('inferior_card_id', $to->id)->where('superior_card_id', $superior->id)->first();
			$from_obsolete = App\Obsolete::with(['votes'])->where('inferior_card_id', $from->id)->where('superior_card_id', $superior->id)->first();
			foreach ($from_obsolete->votes as $vote) {

				// Same IP already exists, just delete the older vote
				if (in_array($vote->ip, $to_obsolete->votes->pluck('ip')->toArray()))
					$vote->delete();

				// Redirect the vote to other obsolete relation
				else {
					$vote->obsolete_id = $to_obsolete->id;
					if ($vote->upvote)
						$to_obsolete->upvotes++;
					else 
						$to_obsolete->downvotes++;

					$vote->save();
				}
			}
			$to_obsolete->save();
			$from_obsolete->delete();
		}

		// Move superior obsolete relation to other card
		else {
			App\Obsolete::where('inferior_card_id', $from->id)->where('superior_card_id', $superior->id)->update(['inferior_card_id' => $to->id]);
		}
	}
}

function create_card_from_scryfall($obj, $parent = null, $callbacks = [])
{
	
	if (!isset($obj->type_line))
		return false;

	$match_count = preg_match_all('/((?:\b[^\x{2014}\/]+\b)+)(?: \x{2014} ((?:\b[^\/]+\b)+))?(:? \/\/ )?/u', $obj->type_line, $match);
	if (!$match_count)
		return false;

	/*
		Pattern v1: '/^(.*?)(?: — (.*))?$/'
		Pattern v2: '/^(.+?)(?: — (.+?))?(?: \/\/ (.+?)(?: — (.+))?)?$/'
	*/

	$card = null;

	// Either use a callback to find the card from database ...
	if (isset($callbacks['query'])) {
		$card = $callbacks['query']($obj, $parent);
	}

	// ... Or use oracle_id. Unless it's a split card, in which case find using card name and parent_id
	else {
		$q = App\Card::with(['functionality.group'])->withCount('functionalReprints');

		if ($parent)
			$q = $q->where('main_card_id', $parent->id)->where('name', $obj->name);
		else
			$q = $q->whereNull('main_card_id')->where(function($q) use ($obj) { 
				$q->where('oracle_id', $obj->oracle_id);//->orWhere('name', $obj->name);
			});

		$card = $q->orderBy('created_at', 'desc')->first();
	}
	
	if (!$card)
		$card = App\Card::newModelInstance();

	else {

		/*
			These checks are recommended if 'scryfall-default-cards.json' is being loaded.
			They are not needed with 'scryfall-oracle-cards.json'
		*/
		/*
		// Keep newest multiverse id
		if ($parent === null && $card->multiverse_id && (empty($obj->multiverse_ids) || $card->multiverse_id > $obj->multiverse_ids[0])) 
			return false;
		*/
		/*
		// Preserve latest available image
		if ($image_uri === null || parse_url($image_uri, PHP_URL_PATH) === "/file/scryfall-errors/soon.jpg")
			$image_uri = $card->scryfall_img;
		*/

		// Don't update updated_at field
		$card->timestamps = false;
	}

	// Split cards have multiple faces
	$multiface = (isset($obj->card_faces) && count($obj->card_faces) >= 2);

	$types = array_values(array_filter(explode(" ", implode(" // ", $match[1]))));
	$subtypes = array_values(array_filter(explode(" ", implode(" // ", $match[2]))));

	$card->fill([
		'name' =>  $obj->name,
		'oracle_id' => isset($obj->oracle_id) ? $obj->oracle_id : null,
		'multiverse_id' => empty($obj->multiverse_ids) ? null : $obj->multiverse_ids[0],
		'legalities' => isset($obj->legalities) ? $obj->legalities : [],
		'manacost' => isset($obj->mana_cost) ? $obj->mana_cost : "",
		'cmc' => isset($obj->cmc) ? (double)$obj->cmc : null,
		'supertypes' => array_values(array_intersect($types, array_merge(App\Card::$all_supertypes, ['//']))),
		'types' => array_values(array_diff($types, App\Card::$all_supertypes)),
		'subtypes' => $subtypes,
		'typeline' => $obj->type_line,
		'colors' => isset($obj->colors) ? $obj->colors : [],
		'color_identity' => isset($obj->color_identity) ? $obj->color_identity : [],
		'rules' => isset($obj->oracle_text) ? $obj->oracle_text : "",
		'power' => isset($obj->power) ? $obj->power : null,
		'toughness' => isset($obj->toughness) ? $obj->toughness : null,
		'loyalty' => isset($obj->loyalty) ? $obj->loyalty : null,
		'scryfall_img' => (isset($obj->image_uris) && $obj->image_uris->normal) ? $obj->image_uris->normal : null,
		'scryfall_api' => isset($obj->uri) ? $obj->uri : null,
		'scryfall_link' => isset($obj->scryfall_uri) ? $obj->scryfall_uri : null,
		'main_card_id' => $parent ? $parent->id : null,
		'flip' => (isset($obj->layout) && in_array($obj->layout, ['flip', 'transform']))
	]);

	// Create a few helper columns using existing data
	$new_rules = $card->substituteRules();
	$regroup = !$parent && (!$card->functionality_id || ($new_rules !== $card->substituted_rules));
	$card->substituted_rules = $new_rules;

	$manacost = App\Manacost::createFromManacostString($card->manacost, in_array("Land", $card->types) ? $card->cmc : null);

	$card->cmc = $manacost->cmc;
	$card->manacost_sorted = $manacost->manacost_sorted;

	if ($parent) {
		$card->multiverse_id = $parent->multiverse_id;
		$card->legalities = $parent->legalities;
		$card->cmc = $manacost->cmc;
		$card->colors = isset($obj->colors) ? $card->colors : $parent->colors;
		$card->color_identity = isset($obj->color_identity) ? $card->color_identity : $parent->color_identity;
		$card->flip = $parent->flip;
		$card->scryfall_api = $parent->scryfall_api;
		$card->scryfall_link = $parent->scryfall_link;

		$card->scryfall_img = $card->scryfall_img ?: $parent->scryfall_img;

		// If parent doesn't have a image, use the first face
		if (!$parent->scryfall_img) {
			$parent->scryfall_img = $card->scryfall_img;
			$parent->save();
		}
	}
	$card->hybridless_cmc = $manacost->hybridless_cmc;

	if ($card->isDirty()) {
		$card->save();
	}

	if ($multiface) {
		$names = [];
		foreach ($obj->card_faces as $card_face) {
			if (create_card_from_scryfall($card_face, $card, $callbacks))
				$names[] = $card_face->name;
		}

		// Remove previous faces no longer present
		if ($card->id)
			App\Card::where('main_card_id', $card->id)->whereNotIn('name', $names)->delete();
	}

	if ($regroup) {
		$card->linkToFunctionality();
	}

	return true;
}

function sum_labels(array $l1, array $l2)
{
	$sum = [];

	foreach ($l1 as $key => $value) {
		if ($key === 'strictly_better')
			continue;

		$sum[$key] = $value || ($l2[$key] ?? false);
	}
	$sum['strictly_better'] = $l1['strictly_better'] && (!isset($l2['strictly_better']) || $l2['strictly_better']);

	return $sum;
}

function create_labels(App\Card $inferior, App\Card $superior, App\Obsolete $obsolete = null)
{

	if (count($inferior->cardFaces) > 0) {

		$labels = [];

		foreach ($inferior->cardFaces as $face) {

			// Only create labels for the face that is better, ignore the other(s)
			if ($superior->isEqualOrBetterThan($face))
				$labels = sum_labels(create_labels($face, $superior, $obsolete), $labels);
		}
		return $labels;
	}

	if (count($superior->cardFaces) > 0) {

		$labels = [];

		foreach ($superior->cardFaces as $face) {
			if ($face->isEqualOrBetterThan($inferior))
				$labels = sum_labels(create_labels($inferior, $face, $obsolete), $labels);

			// Better flip card must only have better first face, so break here
			if ($face->flip)
				break;
		}
		return $labels;
	}

	$labels = [
		'more_colors' => (count($superior->colors) > count($inferior->colors)),
		'more_colored_mana' => $superior->compareColoredCost($inferior) > 0 && $superior->compareAlternativeCosts($inferior) > 0,
		'supertypes_differ' => (count($superior->supertypes) != count($inferior->supertypes) || array_diff($superior->supertypes, $inferior->supertypes)),
		'types_differ' => (count($superior->types) != count($inferior->types) || array_diff($superior->types, $inferior->types)),
		'subtypes_differ' => (count($superior->subtypes) != count($inferior->subtypes) || array_diff($superior->subtypes, $inferior->subtypes)),
		'less_colors' => (count($superior->colors) < count($inferior->colors)),
		'downvoted' => ($obsolete && ($obsolete->upvotes - $obsolete->downvotes) <= -10)
	];

	$strictly_better = true;
	foreach ($labels as $label => $value) {
		if ($value)
			$strictly_better = false;
	}

	$labels['strictly_better'] = $strictly_better;
	return $labels;
}

function create_obsolete(App\Card $inferior, App\Card $superior, $cascade_to_groups = true)
{
	// Confirm this relation doesn't already exist (to prevent timestamp touching)
	if (in_array($superior->functionality_id, $inferior->superiors->pluck('functionality_id')->toArray()))
		return false;

	$superior->loadMissing('functionality');
	$inferior->loadMissing('functionality');

	DB::transaction(function() use ($inferior, $superior, $cascade_to_groups) {

		// Create obsolete, but only if not referring to same functionality group.
		// In such case, labels will still be created, but obsolete_id will be null.
		$obsolete = null;
		if ($superior->functionality->group_id !== $inferior->functionality->group_id)
			$obsolete = App\Obsolete::firstOrCreate([
				'superior_functionality_group_id' => $superior->functionality->group_id,
				'inferior_functionality_group_id' => $inferior->functionality->group_id,
			]);
	
		create_labeling($inferior, $superior, $obsolete, $cascade_to_groups);
	});
	return true;
}

function create_labeling($inferior, $superior, $obsolete = null, $cascade_to_groups = true) {

	// Add labels
	$changes = $superior->functionality->inferiors()->syncWithoutDetaching([
		$inferior->functionality_id => [
			'labels' => create_labels($inferior, $superior, $obsolete), 
			'obsolete_id' => $obsolete ? $obsolete->id : null
		]
	]);

	// If new association was created, touch inferior to put it first in Browse page
	if (in_array($inferior->functionality_id, $changes['attached'])) 
		$inferior->touch();

	// Add labels for other functionalities in the group
	if ($cascade_to_groups) {

		$inferior_list = $inferior->functionality->typevariants()->with(['cards' => function($q) { $q->whereNull('main_card_id'); }])->get();
		$superior_list = $superior->functionality->typevariants()->with(['cards' => function($q) { $q->whereNull('main_card_id'); }])->get();

		remove_functionalities_from_external_suggestions($inferior_list, $superior_list);

		// Add all inferior duplicates to all superiors
		foreach ($superior_list as $superior_item) {

			$inferiors = [];
			foreach ($inferior_list as $inferior_item) {

				$inferiors[$inferior_item->id] = [
					'labels' => create_labels($inferior_item->cards->first(), $superior_item->cards->first(), $obsolete), 
					'obsolete_id' => $obsolete ? $obsolete->id : null
				];
			}

			$superior_item->inferiors()->syncWithoutDetaching($inferiors);
		}

	}
	else
		remove_cards_from_external_suggestions([$inferior->name], [$superior->name]);

}

function remove_functionalities_from_external_suggestions($inferior_functionalities, $superior_functionalities) {

	$superior_names = [];
	foreach ($superior_functionalities as $functionality) {
		$superior_names = array_merge($superior_names, $functionality->cards->pluck('name')->all());
	}

	$inferior_names = [];
	foreach ($inferior_functionalities as $functionality) {
		$inferior_names = array_merge($inferior_names, $functionality->cards->pluck('name')->all());
	}

	remove_cards_from_external_suggestions($inferior_names, $superior_names);

}

function remove_cards_from_external_suggestions($inferior_names, $superior_names) {

	$suggestions = App\Suggestion::whereIn('Inferior', $inferior_names)->get();
	foreach ($suggestions as $suggestion) {
		$suggestion->removeSuperiorNames($superior_names);
	}
}

function get_line_count($filename) {

	$count = 0;
	if ($fp = fopen($filename, 'r')) {

		while (!feof($fp)) {
			fgets($fp);
			$count++;
		}

		fclose($fp);
	}
	return $count;
}

function create_obsoletes(&$count, $using_analysis = false, $progress_callback = null) {

	if ($progress_callback === null)
		$progress_callback = function($cardcount, $at, $card = null, $betters = null) { };

	DB::transaction(function () use ($progress_callback, &$count, $using_analysis) {

	$obsoletion_attributes = [
		'name',
		'cards.id',
		'supertypes',
		'types',
		'subtypes',
		'colors',
		'color_identity',
		'manacost_sorted',
		'cmc',
		'hybridless_cmc',
		'main_card_id',
		'flip',
		'substituted_rules',
		'manacost',
		'power',
		'power_numeric',
		'toughness',
		'toughness_numeric',
		'loyalty',
		'loyalty_numeric',
		'functionality_id'
	];

	$queryAll = App\Card::select($obsoletion_attributes)->with(['functionality'])
		->whereNull('main_card_id')
		->whereDoesntHave('cardFaces')
		->orderBy('cards.id', 'asc');

	if ($using_analysis)
		$queryAll = $queryAll->with(['functionality.variablevalues', 'functionality.excerpts' => function($q) {
			$q/*->select(['excerpts.id', 'positive'])*/->with(['variables', 'superiors.variables', 'inferiors.variables']);
		}]);

	$cardcount = $queryAll->count();
	$progress = 0;

	$progress_callback($cardcount, $progress);

	$allcolors = ["{W}","{B}","{U}","{R}","{G}", "{C}"];

	//$allexcerpts = $using_analysis ? App\Excerpt::where(function($q) { $q->where('positive', 1)->orWhere('positive', 0); })->orderBy('text')->get()->groupBy(['positive', 'regex'])->all() : null;

	$queryAll->chunk(100, function($cards) use ($using_analysis, $cardcount, $progress_callback, &$count, &$progress, $obsoletion_attributes, $allcolors) {

	foreach ($cards as $card) {

		$q = App\Card::select($obsoletion_attributes)->with(['functionality'])
		//	->whereJsonContains('supertypes', $card->supertypes)
		//	->whereJsonLength('supertypes', count($card->supertypes))
		//	->where('id', "!=", $card->id)
			->where('functionality_id', "!=", $card->functionality_id)
			->whereDoesntHave('cardFaces')
			->where(function($q) {
				$q->where('flip', 0)->orWhereNotNull('cmc');
			});

		if (!$using_analysis)
			$q = $q->where('substituted_rules', $card->substituted_rules);

		
	/*	// Can't do rule analysis on empty rules
		else if ($card->substituted_rules == '')
			$q = $q->where('substituted_rules', '!=', '');
	*/	
	
	
		else {
			$q->with(['functionality.variablevalues', 'functionality.excerpts' => function($q) {
				$q->/*select(['excerpts.id', 'positive'])->*/with(['variables', 'superiors.variables', 'inferiors.variables']);
			}]);

			// Must have differing rules text
			$q = $q->where('substituted_rules', '!=', $card->substituted_rules);

			$excerpts = $card->functionality->excerpts;

			$q = $q->whereHas('functionality', function($q) use ($excerpts) {

				// If the inferior doesn't have any excerpts, superior must have some excerpts (that are all positive)
				if ($excerpts->isEmpty())
					$q->has('excerpts')
					->whereDoesntHave('excerpts', function($q) use ($excerpts) {
						$q->where('positive', '=', 0)->orWhereNull('positive');
					});
				else {

					// Must have all non-negative excerpts the inferior has
					// ... or the non-negative excerpt must be an inferior
					$non_negative_ids = $excerpts->where('positive', '!==', 0)->pluck('id')->toArray();

					$non_positives = $excerpts->where('positive', '!==', 1);
					$non_positive_ids =  $non_positives->pluck('id')->toArray();
					foreach ($non_positives as $excerpt) {
						$non_positive_ids = array_merge($non_positive_ids, $excerpt->superiors->pluck('id')->toArray());
					}

					if (count($non_negative_ids) > 0) {

						$q->whereHas('excerpts',  function($q) use ($non_negative_ids) {

							$q->whereIn('excerpts.id', $non_negative_ids)
							->orWhereHas('inferiors', function($q) use ($non_negative_ids) {
								$q->whereIn('excerpts.id', $non_negative_ids);
							});

						}, '>=', count($non_negative_ids));
					}

					// Doesnt have excerpts that are non-positive and not part of inferior card
					// ... and not superior to 
					
					$q->whereDoesntHave('excerpts', function($q) use ($non_positive_ids) {

						$q->where(function($q) {
							$q->where('positive', '=', 0)
							->orWhereNull('positive');
						});

						if (count($non_positive_ids) > 0) {

							$q->where(function($q) use ($non_positive_ids) {
								$q->whereIn('excerpts.id', $non_positive_ids);
							}, null, null, 'AND NOT');
						}
					});
				}	

			});
		}

		// Sorcery may be substituted by an Instant
		if (in_array("Sorcery", $card->types)) {

			$q = $q->where(function($q) use ($card) {

				$substitute_types = $card->types;

				array_splice($substitute_types, array_search("Sorcery", $substitute_types), 1, ["Instant"]);

				// $this->comment("Found sorcery id ". $card->id . " subsituting: " . implode(" ", $substitute_types) . " originial " . implode(" ", $card->types));

				$q->whereJsonContains('types', $card->types)
					->orWhereJsonContains('types', $substitute_types);

			});
		}
		
		// Creatures are compared to creatures, however, they may have other types aswell
		else if (in_array("Creature", $card->types)) {
			$q = $q->whereJsonContains('types', "Creature");
		}

		// Others follow a stricter policy
		else {
			$q = $q->whereJsonContains('types', $card->types)
				->whereJsonLength('types', count($card->types));
		}

		// Musn't have colors the worse card hasn't eithers
		/*foreach (array_diff($allcolors, $card->colors) as $un_color) {
			$q = $q->whereJsonDoesntContain('colors', $un_color);
		}*/

		if ($card->cmc === null)
			$q = $q->whereNull('cmc');
		else
			$q = $q->where('cmc', '<=', $card->cmc)->where('hybridless_cmc', '<=', $card->hybridless_cmc);

		// Creatures need additional rules
		// Either power, toughness or cmc has to be better
		if ($card->power !== null) {

			if (is_numeric($card->power))
				$q = $q->where('power_numeric', '>=', $card->power_numeric);
			else
				$q = $q->where('power', '=', $card->power);
		}
		else
			$q = $q->whereNull('power');

		if ($card->toughness !== null) {
			if (is_numeric($card->toughness))
				$q = $q->where('toughness_numeric', '>=', $card->toughness_numeric);
			else
				$q = $q->where('toughness', '=', $card->toughness);
		}
		else
			$q = $q->whereNull('toughness');

		// Loyalty
		if ($card->loaylty !== null) {
			if (is_numeric($card->loyalty))
				$q = $q->where('loyalty_numeric', '>=', $card->loyalty_numeric);
			else
				$q = $q->where('loyalty', '=', $card->loyalty);
		}
		else
			$q = $q->whereNull('loyalty');

		// Manacost
		if (!empty($card->manacost_sorted)) {
			foreach ($card->manacost_sorted as $symbol => $amount) {
				$q = $q->where(function($q) use ($symbol, $amount){
					$q->whereNull('manacost_sorted->' . $symbol)
						->orWhere('manacost_sorted->' . $symbol, '<=', $amount);
				});
			}

			$uncolors = array_diff($allcolors, array_keys($card->manacost_sorted));
			foreach ($uncolors as $symbol) {
				$q = $q->whereNull('manacost_sorted->' . $symbol);
			}
		}
		else
			$q = $q->whereJsonLength('manacost_sorted', 0);
		
		/*
		if (!empty($card->manacost_sorted)) {
			$colors = array_intersect($allcolors, array_keys($card->manacost_sorted));
			foreach ($colors as $symbol) {
				$q = $q->where(function($q) use ($symbol, $card){
					$q->whereNull('mana_' . $symbol[1])
						->orWhere('mana_' . $symbol[1], '<=', $card->manacost_sorted[$symbol]);
				});
			}

			$uncolors = array_diff($allcolors, $colors);
			foreach ($uncolors as $symbol) {
				$q = $q->whereNull('mana_' . $symbol[1]);
			}
		}
		else
			$q = $q->whereJsonLength('manacost_sorted', 0);
		*/

		// dd(\Str::replaceArray('?', $q->getBindings(), $q->toSql()));


		$q->orderBy('cards.id', 'asc')->chunk(1000, function($betters) use ($card, $using_analysis, $progress_callback, $cardcount, $progress, &$count) {

			// Filter out any better cards that cost more colored mana

			// echo "Found betters: " . count($betters) . PHP_EOL; // for debugging

			if ($using_analysis)
				$betters = $betters->filter(function($better) use ($card) {
					return ($better->compareCost($card, true, false) <= 0 && 
							$better->isBetterByRuleAnalysisThan($card));
				});

			// No rule analysis (default), 
			// $better must prove to be better in some defined category (it's already atleast eqaul at this point)
			else {

				$betters = $betters->filter(function($better) use ($card) {

					$mana_comparison = $better->compareCost($card, true, false);
					if ($mana_comparison > 0 || $mana_comparison < -1)
						return false;

					// Split card is better, even if everything else matches
					if ($card->main_card_id === null && $better->main_card_id !== null)
						return true;

					if ($card->compareCost($better, false, false) === 1)
						return true;
					if ($mana_comparison === -1)
						return true;

					if ($card->hasStats()) {

						// Power and toughness are quaranteed to be atleast equal by this point, so just check for greatness
						$more_power = is_numeric($better->power) ? ($card->power_numeric < $better->power_numeric) : false;
						$more_toughness = is_numeric($better->toughness) ? ($card->toughness_numeric < $better->toughness_numeric) : false;

						return ($more_power || $more_toughness);
					}

					if ($card->hasLoyalty()) {
						return is_numeric($card->loyalty) ? ($card->loyalty_numeric < $better->loyalty_numeric) : false;
					}

					if (in_array("Instant", $better->types) && in_array("Sorcery", $card->types)) {
						return true;
					}

					// $this->comment("#" . $card->id . " " . $card->name . " is not better than #" . $better->id . " " . $better->name);

					return false;
				});
			}

			foreach ($betters as $better) {

				if ($better->main_card_id) {
					//$this->comment("Would create " . $card->name . " -> " .$better->name . " (". $better->mainCard->name . ")");
					create_obsolete($card, $better->mainCard, false);
				}
				else
					create_obsolete($card, $better, false);
				$count++;
			}
			
			$progress_callback($cardcount, $progress, $card, $betters);

		});

		$progress++;
		$progress_callback($cardcount, $progress, $card);

	}
	});
	});

}
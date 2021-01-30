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

			$to_obsolete = App\Obsolete::with(['suggestions'])->where('superior_card_id', $to->id)->where('inferior_card_id', $inferior->id)->first();
			$from_obsolete = App\Obsolete::with(['suggestions'])->where('superior_card_id', $from->id)->where('inferior_card_id', $inferior->id)->first();
			foreach ($from_obsolete->suggestions as $suggestion) {

				// Same IP already exists, just delete the older vote
				if (in_array($suggestion->ip, $to_obsolete->suggestions->pluck('ip')->toArray()))
					$suggestion->delete();

				// Redirect the vote to other obsolete relation
				else {
					$suggestion->obsolete_id = $to_obsolete->id;
					if ($suggestion->upvote)
						$to_obsolete->upvotes++;
					else
						$to_obsolete->downvotes++;
					$suggestion->save();
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
			foreach ($from_obsolete->suggestions as $vote) {

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
		$q = App\Card::query();

		if ($parent)
			$q = $q->where('main_card_id', $parent->id)->where('name', $obj->name);
		else
			$q = $q->whereNull('main_card_id')->where(function($q) use ($obj) { 
				$q->where('oracle_id', $obj->oracle_id)->orWhere('name', $obj->name);
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
		'cmc' => isset($obj->cmc) ? ceil($obj->cmc) : null,
		'supertypes' => array_values(array_intersect($types, array_merge(App\Card::$all_supertypes, ['//']))),
		'types' => array_values(array_diff($types, App\Card::$all_supertypes)),
		'subtypes' => $subtypes,
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
	$card->substituted_rules = $card->substituteRules();
	$card->manacost_sorted = $card->calculateColoredManaCosts();

	if ($parent) {
		$card->multiverse_id = $parent->multiverse_id;
		$card->legalities = $parent->legalities;
		$card->cmc = $card->calculateCmcFromCost();
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
		'more_colored_mana' => $superior->costsMoreColoredThan($inferior),
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

function create_obsolete(App\Card $inferior, App\Card $superior, $cascade_to_reprints = true)
{
	// Confirm this relation doesn't already exist (to prevent timestamp touching)
	if (in_array($superior->id, $inferior->superiors->pluck('id')->toArray()))
		return false;

	$labels = create_labels($inferior, $superior);

	$changes = $superior->inferiors()->syncWithoutDetaching([$inferior->id => ['labels' => $labels]]);

	// If new association was created, touch inferior to put it first in Browse page
	if (in_array($inferior->id, $changes['attached'])) 
		$inferior->touch();

	// Handle reprints
	if ($cascade_to_reprints) {

		// Find duplicates of inferior
		$inferior_list = $inferior->functionalReprints->pluck('id');
		$inferior_list[] = $inferior->id;

		$inferiors = [];
		foreach ($inferior_list as $addable_id) {
			$inferiors[$addable_id] = ['labels' => $labels];
		}

		// Add all inferior duplicates to all superiors
		if (count($superior->functionalReprints) == 0)
			$superior->inferiors()->syncWithoutDetaching($inferiors);

		else {
			foreach ($superior->functionalReprints as $superior_item) {
				$superior_item->inferiors()->syncWithoutDetaching($inferiors);
			}
		}
	}
	return true;
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
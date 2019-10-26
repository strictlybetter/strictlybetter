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

function get_formats()
{
	$samplecard = App\Card::whereNull('main_card_id')->first();
	return $samplecard ? array_keys($samplecard->legalities) : [];
}

function make_format_list()
{
	// Make list of supported formats
	$formats = get_formats();
	asort($formats);

	$formatlist = ['' => 'Any Format'];
	$formatlist = array_merge($formatlist, make_select_options($formats));

	return $formatlist;
}

function create_card_from_scryfall($obj, array $supertypes, $parent = null)
{
	if (!isset($obj->type_line) || !preg_match('/^(.*?)(?: — (.*))?$/', $obj->type_line, $match))
		return false;

	$card = App\Card::firstOrNew(['name' =>  $obj->name]);

	// Keep newest multiverse id
	if ($card->exists && $parent === null && $card->multiverse_id && (empty($obj->multiverse_ids) || $card->multiverse_id > $obj->multiverse_ids[0])) 
		return false;

	// Don't update updated_at field
	if ($card->exists)
		$card->timestamps = false;

	// Split cards have multiple faces
	$multiface = (isset($obj->card_faces) && count($obj->card_faces) >= 2);

	$types = explode(" ", $match[1]);
	$subtypes = isset($match[2]) ? explode(" ", $match[2]) : [];

	$card->fill([
		'multiverse_id' => empty($obj->multiverse_ids) ? null : $obj->multiverse_ids[0],
		'legalities' => isset($obj->legalities) ? $obj->legalities : [],
		'manacost' => isset($obj->mana_cost) ? $obj->mana_cost : "",
		'cmc' => isset($obj->cmc) ? ceil($obj->cmc) : null,
		'supertypes' => array_intersect($types, $supertypes),
		'types' => array_diff($types, $supertypes),
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

	if ($parent) {
		$card->multiverse_id = $parent->multiverse_id;
		$card->legalities = $parent->legalities;
		$card->cmc = $card->calculateCmcFromCost();
		$card->colors = isset($obj->colors) ? $card->colors : $parent->colors;
		$card->color_identity = isset($obj->color_identity) ? $card->color_identity : $parent->color_identity;
		$card->flip = $parent->flip;
		$card->scryfall_img = $parent->scryfall_img;
		$card->scryfall_api = $parent->scryfall_api;
		$card->scryfall_link = $parent->scryfall_link;
	}

	// Create a few helper columns using existing data
	$card->substituted_rules = $card->substituteRules;
	$card->manacost_sorted = $card->colorManaCounts;

	if ($card->isDirty()) {
		$card->save();
	}

	if ($multiface) {
		foreach ($obj->card_faces as $card_face) {
			create_card_from_scryfall($card_face, $supertypes, $card);
		}
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
			if ($superior->isNotWorseThan($face))
				$labels = sum_labels(create_labels($face, $superior, $obsolete), $labels);
		}
		return $labels;
	}

	if (count($superior->cardFaces) > 0) {

		$labels = [];

		foreach ($superior->cardFaces as $face) {
			if ($face->isNotWorseThan($inferior))
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
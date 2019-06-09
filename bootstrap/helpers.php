<?php

function escapeLike(string $value, string $char = '\\')
{
	return str_replace(
		[$char, '%', '_'],
		[$char.$char, $char.'%', $char.'_'],
		$value
	);
}

function make_format_list()
{
	// Make list of supported formats
	$formatlist = ['' => 'Any Format'];
	foreach (App\Card::$formats as $formatname) {
		$formatlist[$formatname] = ucfirst($formatname);
	}
	return $formatlist;
}

function create_labels(App\Card $inferior, App\Card $superior)
{

	$labels = [
		'more_colors' => (count($superior->colors) > count($inferior->colors)),
		'more_colored_mana' => $superior->costsMoreColoredThan($inferior),
		'supertypes_differ' => (count($superior->supertypes) != count($inferior->supertypes) || array_diff($superior->supertypes, $inferior->supertypes)),
		'types_differ' => (count($superior->types) != count($inferior->types) || array_diff($superior->types, $inferior->types)),
		'subtypes_differ' => (count($superior->subtypes) != count($inferior->subtypes) || array_diff($superior->subtypes, $inferior->subtypes)),
		'less_colors' => (count($superior->colors) < count($inferior->colors))
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
	$labels = create_labels($inferior, $superior);

	$superior->inferiors()->syncWithoutDetaching([$inferior->id => ['labels' => $labels]]);

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
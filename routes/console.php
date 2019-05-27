<?php

use Illuminate\Foundation\Inspiring;

use App\Card;
use App\Obsolete;
use App\FunctionalReprint;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

Artisan::command('load-scryfall', function () {
    
    $filename = 'scryfall-default-cards.json';
    $type_pattern = '/^(.*?)(?: — (.*))?$/';
    $supertypes = ["Basic", "Elite", "Host", "Legendary", "Ongoing", "Snow", "World"];

	if ($fh = fopen($filename, 'r')) {

		 $this->comment("Loading cards...");
		 $count = 0;

		while (!feof($fh)) {
			$line = rtrim(trim(fgets($fh)), ',');
			$obj = json_decode($line);
			
			// Check validity of the card
			if ($obj === null || empty($obj->multiverse_ids))
				continue;

			if (!preg_match($type_pattern, $obj->type_line, $match))
				continue;

			$card = Card::firstOrNew(['name' =>  $obj->name]);

			// Keep newest multiverse id
			if ($card->exists && $card->multiverse_id > $obj->multiverse_ids[0])
				continue;

			$types = explode(" ", $match[1]);
			$subtypes = isset($match[2]) ? explode(" ", $match[2]) : [];

			$card->fill([
				'multiverse_id' => $obj->multiverse_ids[0],
				'legalities' => $obj->legalities,
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
				'loyalty' => isset($obj->loyalty) ? $obj->loyalty : null
			]);

			if ($card->isDirty()) {
				$card->save();
				$count++;
			}
		}
		fclose($fh);

		$this->comment($count . " cards loaded.");
	}
	else {	
		$this->comment("Could not read file: " . $filename);
	}
	

})->describe('Load scryfall cards from json in to the local database');


Artisan::command('populate-functional-reprints', function () {

	$this->comment("Looking for duplicate families...");

	$results = Card::where('name', 'not like', '% // %')->get()->groupBy('functionalReprintLine')->reject(function($item) {
		return (count($item) <= 1);
	});

	$this->comment(count($results) . " duplicate families found. Populating...");

	// Clear previous entries
	FunctionalReprint::query()->delete();

	foreach ($results as $reprint_group) {

		$sample = $reprint_group[0];

		$group = FunctionalReprint::create([
			'typeline' => $sample->typeLine,
			'manacost' => $sample->manacost, 
			'power' => $sample->power, 
			'toughness' => $sample->toughness, 
			'loyalty' => $sample->loyalty, 
			'rules' => $sample->rules,
		]);

		//$group->cards()->associate($reprint_group->pluck('id'));
		foreach ($reprint_group as $card) {
			$card->functional_reprints_id = $group->id;
			$card->save();
		}
	}
})->describe('Populates functional reprints table based on existing cards');


Artisan::command('create-functional-obsoletes', function () {

	$this->comment("Building additonal suggestions based on functional reprints...");

	$results = FunctionalReprint::with(['cards.inferiors', 'cards.superiors'])->get();
	$old_obsolete_count = Obsolete::count();

	foreach ($results as $reprint_group) {

		$inferior_ids = $reprint_group->cards->pluck('inferiors')->flatten()->pluck('id');
		$superior_ids = $reprint_group->cards->pluck('superiors')->flatten()->pluck('id');

		foreach ($reprint_group->cards as $card) {

			$card->inferiors()->syncWithoutDetaching($inferior_ids);
			$card->superiors()->syncWithoutDetaching($superior_ids);
		}
	}
	$new_obsolete_count = Obsolete::count();

	$this->comment(($new_obsolete_count - $old_obsolete_count) . " new suggestions created.");

})->describe('Creates suggestions based on functional reprints and their existing suggestions');


Artisan::command('remove-bad-suggestions', function () {

	$this->comment("Removing bad suggestions...");
	$count = 0;

	$obsoletes = Obsolete::with(['inferior', 'superior'])->get();
	foreach ($obsoletes as $obsolete) {
		if (!$obsolete->superior->isSuperior($obsolete->inferior)) {

			$this->comment("Removing suggestion: " . $obsolete->inferior->name . " -> " . $obsolete->superior->name);

			$obsolete->delete();
			$count++;
		}
	}
	$this->comment("Removed " . $count . " bad suggestions.");

})->describe('Removes suggestions that no longer pass inferior-superior check');

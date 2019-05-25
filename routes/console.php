<?php

use Illuminate\Foundation\Inspiring;

use App\Card;

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

			// We only need one copy of the card, skip duplicates
			if (Card::where('name', $obj->name)->first())
				continue;

			$types = explode(" ", $match[1]);
			$subtypes = isset($match[2]) ? explode(" ", $match[2]) : [];

			Card::create([
				'name' => $obj->name,
				'multiverse_id' => $obj->multiverse_ids[0],
				'legalities' => $obj->legalities,
				'manacost' => isset($obj->mana_cost) ? $obj->mana_cost : "",
				'cmc' => isset($obj->cmc) ? ceil($obj->cmc) : null,
				'supertypes' => array_intersect($types, $supertypes),
				'types' => array_diff($types, $supertypes),
				'subtypes' => $subtypes
			]);

			$count++;
		}
		fclose($fh);

		$this->comment($count . " cards loaded.");
	}
	else {	
		$this->comment("Could not read file: " . $filename);
	}
	

})->describe('Load scryfall cards from json in to the local database');

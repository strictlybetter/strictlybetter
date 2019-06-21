<?php

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

Artisan::command('load-scryfall', function () {

	$filename = 'scryfall-default-cards.json';
	$type_pattern = '/^(.*?)(?: — (.*))?$/';
	$supertypes = ["Basic", "Elite", "Host", "Legendary", "Ongoing", "Snow", "World"];
	$ignore_layouts = ["planar", "scheme", "token", "double_faced_token", "emblem"];

	if ($fh = fopen($filename, 'r')) {

		$this->comment("Loading cards...");
		$count = 0;

		$bar = $this->output->createProgressBar(get_line_count($filename));

		while (!feof($fh)) {
			$line = rtrim(trim(fgets($fh)), ',');
			$obj = json_decode($line);

			$bar->advance();
			
			// Check validity of the card
			if ($obj === null || in_array($obj->layout, $ignore_layouts))
				continue;

			if (!preg_match($type_pattern, $obj->type_line, $match))
				continue;

			$card = Card::firstOrNew(['name' =>  $obj->name]);

			// Keep newest multiverse id
			if ($card->exists && $card->multiverse_id && (empty($obj->multiverse_ids) || $card->multiverse_id > $obj->multiverse_ids[0]))
				continue;

			// Don't update updated_at field
			if ($card->exists)
				$card->timestamps = false;

			$types = explode(" ", $match[1]);
			$subtypes = isset($match[2]) ? explode(" ", $match[2]) : [];


			$card->fill([
				'multiverse_id' => empty($obj->multiverse_ids) ? null : $obj->multiverse_ids[0],
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
				'loyalty' => isset($obj->loyalty) ? $obj->loyalty : null,
				'scryfall_img' => (isset($obj->image_uris) && $obj->image_uris->normal) ? $obj->image_uris->normal : null,
				'scryfall_api' => isset($obj->uri) ? $obj->uri : null,
				'scryfall_link' => isset($obj->scryfall_uri) ? $obj->scryfall_uri : null
			]);

			// Create a few helper columns using existing data
			$card->substituted_rules = $card->substituteRules;
			$card->manacost_sorted = $card->colorManaCounts;

			if ($card->isDirty()) {
				$card->save();
				$count++;
			}
		}
		fclose($fh);

		$bar->finish();

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
	})->values();

	$this->comment(count($results) . " duplicate families found. Populating...");

	// Clear previous entries and reset auto increment 
	// Note: truncate could be faster, but we have foreign keys that must be cleared on cards table
	FunctionalReprint::query()->delete();
	DB::statement("ALTER TABLE functional_reprints AUTO_INCREMENT = 1");

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

Artisan::command('remove-bad-cards', function () {

	$count = Card::count();
	$cards = Card::whereJsonContains('types', 'Token')->orWhereJsonContains('types', 'Plane')->orWhereJsonContains('types', 'Scheme')->delete();
	$count = $count - Card::count();

	$this->comment("Removed " . $count . " bad cards");

});


Artisan::command('create-functional-obsoletes', function () {

	$this->comment("Building additonal suggestions based on functional reprints...");

	$results = FunctionalReprint::with(['cards.inferiors', 'cards.superiors'])->get();
	$old_obsolete_count = Obsolete::count();

	foreach ($results as $reprint_group) {

		if (count($reprint_group->cards) < 2)
			continue;

		$labels = create_labels($reprint_group->cards[0], $reprint_group->cards[1]);

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

Artisan::command('create-labels', function () {
	$obsoletes = Obsolete::with(['inferior', 'superior'])->get();

	foreach ($obsoletes as $obsolete) {
		$obsolete->labels = create_labels($obsolete->inferior, $obsolete->superior);
		$obsolete->save();
	}
});

Artisan::command('remove-bad-suggestions', function () {

	$this->comment("Removing bad suggestions...");
	$count = 0;

	$obsoletes = Obsolete::with(['inferior', 'superior'])->get();
	foreach ($obsoletes as $obsolete) {
		if (!$obsolete->superior->isNotWorseThan($obsolete->inferior)) {

			$this->comment("Removing suggestion: " . $obsolete->inferior->name . " -> " . $obsolete->superior->name);

			$obsolete->delete();
			$count++;
		}
	}
	$this->comment("Removed " . $count . " bad suggestions.");

})->describe('Removes suggestions that no longer pass inferior-superior check');


Artisan::command('create-obsoletes', function () {

	$this->comment("Looking for better cards...");

	$cards = Card::whereNull('loyalty')
		->where('name', 'not like', "% // %")
		->orderBy('id', 'asc')->get();

	$allcolors = ["W","B","U","R","G"];

	$bar = $this->output->createProgressBar(count($cards));

	$bar->start();
	$count = 0;
	$old_obsolete_count = Obsolete::count();

	foreach ($cards as $card) {

		$betters = Card::where('substituted_rules', $card->substituted_rules)
			->whereJsonContains('supertypes', $card->supertypes)
			->whereJsonLength('supertypes', count($card->supertypes))
			->where('id', "!=", $card->id);

		// Sorcery may be substituted by an Instant
		if (in_array("Sorcery", $card->types)) {

			$betters = $betters->where(function($q) use ($card) {

				$substitute_types = $card->types;

				array_splice($substitute_types, array_search("Sorcery", $substitute_types), 1, ["Instant"]);

				// $this->comment("Found sorcery id ". $card->id . " subsituting: " . implode(" ", $substitute_types) . " originial " . implode(" ", $card->types));

				$q->whereJsonContains('types', $card->types)
					->orWhereJsonContains('types', $substitute_types);

			})->whereJsonLength('types', count($card->types));
		}
		// Creatures are compared to creatures, however, they may have other types aswell
		else if (in_array("Creature", $card->types)) {
			$betters = $betters->whereJsonContains('types', "Creature");
		}

		// Others follow a stricter policy
		else {
			$betters = $betters->whereJsonContains('types', $card->types)
				->whereJsonLength('types', count($card->types));
		}

		if (!empty($card->subtypes)) {
			$betters = $betters->where(function($q) use ($card) {

				$q->whereJsonLength('subtypes', 0);
				foreach ($card->subtypes as $subtype) {
					$q->orWhereJsonContains('subtypes', $subtype);
				}
			});
		}

		// Musn't have colors the worse card hasn't eithers
		/*foreach (array_diff($allcolors, $card->colors) as $un_color) {
			$betters = $betters->whereJsonDoesntContain('colors', $un_color);
		}*/

		if ($card->functional_reprints_id)
			$betters = $betters->where('functional_reprints_id', "!=", $card->functional_reprints_id);

		if ($card->cmc === null)
			$betters = $betters->whereNull('cmc');
		else
			$betters = $betters->where('cmc', '<=', $card->cmc);

		// Creatures need additional rules
		// Either power, toughness or cmc has to be better
		if ($card->power !== null) {

			if (strpos($card->power, '*') === false)
				$betters = $betters->where('power', '>=', (int)$card->power);
			else
				$betters = $betters->where('power', '=', $card->power);

			if (strpos($card->toughness, '*') === false)
				$betters = $betters->where('toughness', '>=', (int)$card->toughness);
			else
				$betters = $betters->where('toughness', '=', $card->toughness);
		}
		else
			$betters = $betters->whereNull('power')->whereNull('toughness');

			/*	->where(function($q) use ($card) {

					$q->where('power', '>', $card->power)
						->orWhere('toughness', '>', $card->toughness)
						->orWhere('cmc', '<', $card->cmc);
				});*/
		

		if ($card->manacost_sorted !== false) {
			foreach ($card->manacost_sorted as $symbol => $amount) {
				$betters = $betters->where(function($q) use ($symbol, $amount){
					$q->whereNull('manacost_sorted->' . $symbol)
						->orWhere('manacost_sorted->' . $symbol, '<=', $amount);
				});
			}
		}
		else
			$betters = $betters->whereJsonContains('manacost_sorted', false);


		$betters = $betters->orderBy('id', 'asc')->get();

		// Filter out any better cards that cost more colored mana
		if (count($betters) > 0 && $card->cmc !== null) {

			$betters = $betters->filter(function($better) use ($card) {
				return (!$better->costsMoreColoredThan($card));
			})->values();

			$betters = $betters->filter(function($better) use ($card) {

				if ($card->cmc > $better->cmc || $card->costsMoreColoredThan($better))
					return true;

				if ($card->hasStats()) {
					return ($card->power < $better->power || $card->toughness < $better->toughness);
				}

				if ($card->hasLoyalty()) {
					return ($card->loyalty < $better->loyalty);
				}

				if (in_array("Instant", $better->types) && in_array("Sorcery", $card->types)) {
					return true;
				}

				$this->comment("#" . $card->id . " " . $card->name . " is not better than #" . $better->id . " " . $better->name);

				return false;
			})->values();
			
		}
		


// where('power', 'not like', '%*%')->where('toughness', 'not like', '%*%')
		/*

		$betters = $cards->filter(function($other) use ($card, $colors, $hasLoylaty, $isCreature) {

			if ($other->cmc <= $card->cmc

			if ($other->multiverse_id === $card->multiverse_id || $other->functional_reprints_id === $card->functional_reprints_id)
				return false;

			if (implode("", $other->colors) === $colors &&
				$other->typeLine === $card->typeLine &&
				$other->rules === $card->rules
			) {
				if ($isCreature && ($other->power === '*' || $other->power < $card->power || $other->toughness === '*' || $other->toughness < $card->toughness))
					return false;

				if ($hasLoylaty && $other->loyalty < $card->loyalty)
					return false;

				return true;
			}
			return false;
		});
		*/

		foreach ($betters as $better) {
			$this->comment("#" . $card->id . " " . $card->name . " can be upgraded to #" . $better->id . " " . $better->name);
			create_obsolete($card, $better, false);
			$count++;
		}
		
		$bar->advance();
	}
	$bar->finish();
	$this->comment($count . " better cards found. " . (Obsolete::count() - $old_obsolete_count) . "  new records created for database. ");

})->describe('Populates obsoletes table with programmatically findable strictly better cards');

<?php

use App\Card;
use App\Obsolete;
use App\FunctionalReprint;
use App\Cardtype;
use App\Labeling;

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

Artisan::command('load-scryfall {callbacks?}', function ($callbacks = []) {

	$filename = 'scryfall-oracle-cards.json';

	if (!is_array($callbacks))
		$callbacks = [];

	if (!is_file($filename)) {
		$this->comment("Scryfall bulk-data file: " . $filename . " doesn't exist. Full update is recommended: php artisan full-update");
		return 1;
	}

	if ($fh = fopen($filename, 'r')) {

		$this->comment("Loading cards...");
		$count = 0;

		$bar = $this->output->createProgressBar(get_line_count($filename));

		DB::transaction(function () use ($fh, $bar, &$count, $callbacks) {

			while (!feof($fh)) {
				$line = rtrim(trim(fgets($fh)), ',');
				$obj = json_decode($line);

				$bar->advance();
				
				// Check validity of the card
				if ($obj === null || !isset($obj->oracle_id) ||
					!isset($obj->layout) || in_array($obj->layout, Card::$ignore_layouts) || 
					!isset($obj->type_line) || in_array($obj->type_line, Card::$ignore_types))
					continue;

				if (create_card_from_scryfall($obj, null, $callbacks))
					$count++;
			}

		});
		fclose($fh);

		$bar->finish();

		$this->comment($count . " cards loaded.");
	}
	else {
		$this->comment("Could not read file: " . $filename . ". Check file permissions.");
		return 1;
	}
	return 0;

})->describe('Load scryfall cards from json in to the local database');

Artisan::command('remove-old-spoilers', function () {

	// If some older cards share oracle_id, they are duplicates with different names (invented by Scryfall devs before the card name is revealed)
	// The duplicates with invented names should be removed, which we can do by comparing auto incremented ids.
	// The removable duplicates will have lower ids.

	$this->comment("Looking for old spoilers to remove...");
	
	$earlier_count = Card::count();

	// Delete any cards without oracle_id (with main_card_id)
	Card::whereNull('main_card_id')->whereNull('oracle_id')->delete();
	$count = $earlier_count - Card::count();

	DB::transaction(function () use (&$count) {

		$duplicate_groups = Card::whereNull('main_card_id')->get()->groupBy('oracle_id')->reject(function($group) {
			return (count($group) < 2);
		})->values();

		foreach ($duplicate_groups as $duplicate_group) {
			$duplicate_group = $duplicate_group->sortByDesc('id')->values();
			$latest = $duplicate_group->first();

			$duplicate_group = $duplicate_group->slice(1)->values();

			// Move obsoletes from old spoilers to latest card
			$latest->load(['inferiors', 'superiors']);
			foreach ($duplicate_group as $duplicate) {
				migrate_obsoletes($duplicate, $latest);
			}

			$count += count($duplicate_group);
			$ids = $duplicate_group->pluck('id')->toArray();
			Card::whereIn('id', $ids)->delete();
		}

		// Remove orphaned functional reprints
		FunctionalReprint::whereHas('cards', null, '<=', 1)->delete();
	});

	$this->comment("Removed " . $count . " old spoilers");

})->describe('Remove all old spoiler cards after new one exists');

Artisan::command('remove-functional-reprints', function () {

	$this->comment("Removing previous entries...");

	// Clear previous entries and reset auto increment 
	// Note: truncate could be faster, but we have foreign keys that must be cleared on cards table
	FunctionalReprint::query()->delete();
	DB::statement("ALTER TABLE functional_reprints AUTO_INCREMENT = 1");

})->describe('Remove all functional reprints and reset autoincrement');


Artisan::command('populate-functional-reprints', function () {

	$this->comment("Looking for duplicate families...");

	$results = Card::whereNull('main_card_id')->get()->groupBy('functionality_line')->reject(function($item) {
		return (count($item) <= 1);
	})->values();

	$this->comment(count($results) . " duplicate families found. Populating...");

	$count = FunctionalReprint::count();
	$card_count = 0;

	DB::transaction(function () use ($results, &$card_count) {
		foreach ($results as $reprint_group) {

			$sample = $reprint_group[0];

			$group = FunctionalReprint::FirstOrCreate([
				'typeline' => $sample->typeline,
				'manacost' => $sample->manacost, 
				'power' => $sample->power, 
				'toughness' => $sample->toughness, 
				'loyalty' => $sample->loyalty, 
				'rules' => $sample->substituted_rules,
			]);

			//$group->cards()->associate($reprint_group->pluck('id'));
			foreach ($reprint_group as $card) {
				if ($card->functional_reprints_id != $group->id) {
					$card->functional_reprints_id = $group->id;
					$card->save();
					$card_count++;
				}
			}
		}
	});

	$results = FunctionalReprint::count() - $count;
	$this->comment($results . " new families created and " . $card_count . " cards added");

})->describe('Populates functional reprints table based on existing cards');

Artisan::command('remove-bad-cards', function () {

	// Delete cards with types that should be ignored
	// Use 1 = 0 for safety, if $igore_types is empty
	$card_count = Card::count();
	$reprint_count = FunctionalReprint::count();

	DB::transaction(function () {

		$q = Card::whereRaw('1 = 0')->orWhere(function($q) {
			foreach (Card::$ignore_types as $type) {
				$q->orWhereJsonContains('types', $type);
			}
		})->delete();

		// Delete any orphaned functional reprints
		FunctionalReprint::whereHas('cards', null, '<=', 1)->delete();
	});

	$card_count = $card_count - Card::count();
	$reprint_count = $reprint_count - FunctionalReprint::count();

	$this->comment("Removed " . $card_count . " bad cards and " . $reprint_count . " functional reprint families");

});

Artisan::command('create-labels', function () {
	DB::transaction(function () {
		$labelings = Labeling::with([
			'inferiors', 
			'superiors', 
			'obsolete'
		])->get();

		foreach ($labelings as $labeling) {
			$labeling->timestamps = false;
			$labeling->labels = create_labels($labeling->inferiors->first(), $labeling->superiors->first(), $labeling->obsolete);
			$labeling->save(['touch' => false]);
		}
	});
})->describe('Re-creates labels for obsolete cards');

Artisan::command('remove-bad-suggestions', function () {

	$this->comment("Removing bad suggestions...");
	$count = 0;

	DB::transaction(function () use (&$count) {

		$obsoletes = Obsolete::with(['inferiors', 'superiors'])->get();
	
		foreach ($obsoletes as $obsolete) {
			if (!$obsolete->superiors->first()->isEqualOrBetterThan($obsolete->inferiors->first())) {

				$this->comment("Removing suggestion family: " . $obsolete->inferiors->first()->name . " -> " . $obsolete->superiors->first()->name);

				$obsolete->delete();
				$count++;
			}
		}
	});
	$this->comment("Removed " . $count . " bad suggestions.");

})->describe('Removes suggestions that no longer pass inferior-superior check');

Artisan::command('recreate-substitute-rules', function () {

	$this->comment("Updating cards...");
	$count = 0;

	$cards = Card::all();
	$bar = $this->output->createProgressBar(count($cards));

	DB::transaction(function () use ($cards, $bar, &$count) {
		foreach ($cards as $card) {

			$bar->advance();
			$new_rules = $card->substituteRules();

			if ($new_rules !== $card->substituted_rules) {
				$card->substituted_rules = $new_rules;

				$card->save();
				$count++;
			}
		}
	});
	$bar->finish();
	$this->comment("Updated " . $count . " cards with new rule substitutions.");

})->describe('Re-creates substitued rules for existing cards.');

Artisan::command('create-obsoletes', function () {

	$this->comment("Looking for better cards...");

	$obsoletion_attributes = [
		'cards.id',
		'supertypes',
		'types',
		'subtypes',
		'colors',
		'color_identity',
		'manacost_sorted',
		'cmc',
		'main_card_id',
		'flip',
		'substituted_rules',
		'manacost',
		'power',
		'toughness',
		'loyalty',
		'functionality_id'
	];

	$cards = Card::select($obsoletion_attributes)
		->whereNull('main_card_id')
		->whereDoesntHave('cardFaces')
		->orderBy('id', 'asc')->get();

	$allcolors = ["W","B","U","R","G"];

	$bar = $this->output->createProgressBar(count($cards));

	$bar->start();
	$count = 0;
	$old_obsolete_count = Obsolete::count();

	DB::transaction(function () use ($cards, $bar, $obsoletion_attributes, &$count) {

	foreach ($cards as $card) {

		$betters = Card::select($obsoletion_attributes)
			->where('substituted_rules', $card->substituted_rules)
		//	->whereJsonContains('supertypes', $card->supertypes)
		//	->whereJsonLength('supertypes', count($card->supertypes))
			->where('id', "!=", $card->id)
			->where('functionality_id', "!=", $card->functionality_id)
			->whereDoesntHave('cardFaces')
			->where(function($q) {
				$q->where('flip', false)->orWhereNotNull('cmc');
			});

		// Sorcery may be substituted by an Instant
		if (in_array("Sorcery", $card->types)) {

			$betters = $betters->where(function($q) use ($card) {

				$substitute_types = $card->types;

				array_splice($substitute_types, array_search("Sorcery", $substitute_types), 1, ["Instant"]);

				// $this->comment("Found sorcery id ". $card->id . " subsituting: " . implode(" ", $substitute_types) . " originial " . implode(" ", $card->types));

				$q->whereJsonContains('types', $card->types)
					->orWhereJsonContains('types', $substitute_types);

			});
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

		// Musn't have colors the worse card hasn't eithers
		/*foreach (array_diff($allcolors, $card->colors) as $un_color) {
			$betters = $betters->whereJsonDoesntContain('colors', $un_color);
		}*/

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
		

		if (!empty($card->manacost_sorted)) {
			foreach ($card->manacost_sorted as $symbol => $amount) {
				$betters = $betters->where(function($q) use ($symbol, $amount){
					$q->whereNull('manacost_sorted->' . $symbol)
						->orWhere('manacost_sorted->' . $symbol, '<=', $amount);
				});
			}
		}
		else
			$betters = $betters->whereJsonLength('manacost_sorted', 0);


		$betters = $betters->orderBy('id', 'asc')->get();

		// Filter out any better cards that cost more colored mana
		if (count($betters) > 0 && $card->cmc !== null) {

			$betters = $betters->filter(function($better) use ($card) {
				return (!$better->costsMoreColoredThan($card));
			})->values();

			$betters = $betters->filter(function($better) use ($card) {

				// Split card is better, even if everything else matches
				if ($card->main_card_id === null && $better->main_card_id !== null)
					return true;

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

				// $this->comment("#" . $card->id . " " . $card->name . " is not better than #" . $better->id . " " . $better->name);

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
			//$this->comment("#" . $card->id . " " . $card->name . " can be upgraded to #" . $better->id . " " . $better->name);

			if ($better->main_card_id) {
				//$this->comment("Would create " . $card->name . " -> " .$better->name . " (". $better->mainCard->name . ")");
				create_obsolete($card, $better->mainCard, false);
			}
			else
				create_obsolete($card, $better, false);
			$count++;
		}
		
		$bar->advance();
	}
	});
	$bar->finish();
	$this->comment($count . " better cards found. " . (Obsolete::count() - $old_obsolete_count) . "  new records created for database. ");

})->describe('Populates obsoletes table with programmatically findable strictly better cards');


Artisan::command('download-scryfall', function () {

	$filename = 'scryfall-oracle-cards.json';

	$this->comment("Requesting bulkfile metadata...");

	$client = new \GuzzleHttp\Client();
	$request = null;
	try {
		$request = $client->get(config('externals.scryfall.bulk-data'));
	} 
	catch (\GuzzleHttp\Exception\RequestException $e) {
		report($e);
		$this->comment("Download failed");
		return 1;
	}

	if ($request->getStatusCode() != 200) {
		$this->comment("Download failed");
		return 1;
	}

	$response = json_decode($request->getBody(), true);	

	if ($response === null) {
		$this->comment("Failed to parse json");
		return 1;
	}

	$mtime = 0;
	if (is_file($filename))
		$mtime = filemtime($filename);

	foreach ($response["data"] as $bulkfile) {

		if ($bulkfile["type"] !== "oracle_cards")
			continue;

		$updated_at = new DateTime($bulkfile["updated_at"]);

		if ($updated_at->getTimestamp() < $mtime) {
			$this->comment("No need to update.");
			return 1;
		}

		$localfile = fopen($filename, 'w');
		if (!$localfile) {
			$this->comment("Couldn't open " . $filename . " for writing.");
			return 1;
		}

		$this->comment("Downloading " . $filename . "...");

		try {
			$client->get($bulkfile["download_uri"], ['sink' => $localfile]);
		}
		catch (\GuzzleHttp\Exception\RequestException $e) {
			report($e);
			$this->comment("Download failed");
			return 1;
		}

		$this->comment("Download complete");

		return 0;
		
	}
	$this->comment("Couldn't find correct bulk file type");
	return 1;

})->describe('Downloads newest card database from Scryfall');

Artisan::command('download-typedata', function () {

	$downloadable_types = [
		'land-types' => 'Land', 
		'creature-types' => 'Creature',
		'planeswalker-types' => 'Planeswalker',
		'artifact-types' => 'Artifact',
		'spell-types' => 'Spell',
		'enchantment-types' => 'Enchantment'
	];

	$client = new \GuzzleHttp\Client();

	foreach ($downloadable_types as $uri => $type) {

		$this->comment("Downloading type data (". $uri .")...");

		$request = null;
		try {
			$request = $client->get(config('externals.scryfall.catalog') . '/'. $uri);
		}
		catch (\GuzzleHttp\Exception\RequestException $e) {
			report($e);
			$this->comment("Download failed");
			continue;
		}

		if ($request->getStatusCode() != 200) {
			$this->comment("Download failed");
			continue;
		}

		$response = json_decode($request->getBody(), true);	
		if ($response === null || !isset($response["data"])) {
			$this->comment("failed to parse json");
			continue;
		}

		DB::transaction(function () use ($response, $type) {

			foreach ($response["data"] as $key) {
				Cardtype::FirstOrCreate([
					'section' => 'subtype',
					'type' => $type,
					'key' => $key
				]);
			}
		});
		$this->comment("Download complete");
	}

	$this->comment("Update completed");
});

Artisan::command('full-update', function () {

	$this->comment(date('[Y-m-d H:i:s]') . " Full update started");

	if (Artisan::call('download-scryfall', [], $this->getOutput()) !== 0)
		return;

	Artisan::call('download-typedata', [], $this->getOutput());
	Artisan::call('load-scryfall', [], $this->getOutput());
	Artisan::call('populate-functional-reprints', [], $this->getOutput());
	Artisan::call('create-obsoletes', [], $this->getOutput());

})->describe('Performs full update cycle');
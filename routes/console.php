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

	if ($fh = fopen($filename, 'r')) {

		$this->comment("Loading cards...");
		$count = 0;

		$bar = $this->output->createProgressBar(get_line_count($filename));

		while (!feof($fh)) {
			$line = rtrim(trim(fgets($fh)), ',');
			$obj = json_decode($line);

			$bar->advance();
			
			// Check validity of the card
			if ($obj === null || 
				!isset($obj->layout) || in_array($obj->layout, Card::$ignore_layouts) || 
				!isset($obj->type_line) || in_array($obj->type_line, Card::$ignore_types))
				continue;

			if (create_card_from_scryfall($obj))
				$count++;
		}
		fclose($fh);

		$bar->finish();

		$this->comment($count . " cards loaded.");
	}
	else {	
		$this->comment("Could not read file: " . $filename);
	}
	

})->describe('Load scryfall cards from json in to the local database');

Artisan::command('remove-functional-reprints', function () {

	$this->comment("Removing previous entries...");

	// Clear previous entries and reset auto increment 
	// Note: truncate could be faster, but we have foreign keys that must be cleared on cards table
	FunctionalReprint::query()->delete();
	DB::statement("ALTER TABLE functional_reprints AUTO_INCREMENT = 1");

})->describe('Remove all functional reprints and reset autoincrement');


Artisan::command('populate-functional-reprints', function () {

	$this->comment("Looking for duplicate families...");

	$results = Card::whereNull('main_card_id')->get()->groupBy('functionalReprintLine')->reject(function($item) {
		return (count($item) <= 1);
	})->values();

	$this->comment(count($results) . " duplicate families found. Populating...");

	$count = FunctionalReprint::count();
	$card_count = 0;

	foreach ($results as $reprint_group) {

		$sample = $reprint_group[0];

		$group = FunctionalReprint::FirstOrCreate([
			'typeline' => $sample->typeLine,
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

	$results = FunctionalReprint::count() - $count;
	$this->comment($results . " new families created and " . $card_count . " cards added");

})->describe('Populates functional reprints table based on existing cards');

Artisan::command('remove-bad-cards', function () {

	// Delete cards with types that should be ignored
	// Use 1 = 0 for safety, if $igore_types is empty
	$card_count = Card::count();

	$q = Card::whereRaw('1 = 0')->orWhere(function($q) {
		foreach (Card::$ignore_types as $type) {
			$q->orWhereJsonContains('types', $type);
		}
	})->delete();

	$card_count = $card_count - Card::count();

	// Delete any orphaned functional reprints
	$reprint_count = FunctionalReprint::count();
	FunctionalReprint::whereHas('cards', null, '<=', 1)->delete();
	$reprint_count = $reprint_count - FunctionalReprint::count();

	$this->comment("Removed " . $card_count . " bad cards and " . $reprint_count . " functional reprint families");

});


Artisan::command('create-functional-obsoletes', function () {

	$this->comment("Building additonal suggestions based on functional reprints...");

	$results = FunctionalReprint::with(['cards.inferiors', 'cards.superiors'])->get();
	$old_obsolete_count = Obsolete::count();

	foreach ($results as $reprint_group) {

		if (count($reprint_group->cards) < 2)
			continue;

		// Find all relations for all cards in the reprint group (+ their relation labels)
		$inferior_ids = $reprint_group->cards->pluck('inferiors')->flatten()->pluck('pivot.labels', 'id')->toArray();
		$superior_ids = $reprint_group->cards->pluck('superiors')->flatten()->pluck('pivot.labels', 'id')->toArray();

		// Replace 'downvoted' label with false, since are only going to create new relations
		// Other labels should be equal, since the cards are reprints of each other
		foreach ($inferior_ids as $id => $labels) {
			$labels['downvoted'] = false;
			$inferior_ids[$id] = ["labels" => $labels];
		}
		foreach ($superior_ids as $id => $labels) {
			$labels['downvoted'] = false;
			$superior_ids[$id] = ["labels" => $labels];
		}

		// Sync all relations of each card in the group with each other card in the group
		foreach ($reprint_group->cards as $card) {

			// Filter attachable ids here, so we can rely on downvoted = false label
			$inferiors_to_add = array_diff_key($inferior_ids, $card->inferiors->pluck('', 'id')->toArray());
			$superiors_to_add = array_diff_key($superior_ids, $card->superiors->pluck('', 'id')->toArray());

			if (count($inferiors_to_add) > 0) {
				$changes = $card->inferiors()->syncWithoutDetaching($inferiors_to_add);

				// Touch attached inferiors, so they are listed first on Browse page
				if (!empty($changes['attached'])) {
					$inferiors = $card->inferiors()->whereIn('cards.id', $changes['attached'])->get();

					foreach ($inferiors as $inferior) {
						$inferior->touch();
					}
				}	
			}

			if (count($superiors_to_add) > 0) {
				$changes = $card->superiors()->syncWithoutDetaching($superiors_to_add);

				// Touch this card if any new superiors were added, so this card is listed first on Browse page
				if (!empty($changes['attached']))
					$card->touch();
			}
		}
	}
	$new_obsoletes = Obsolete::count() - $old_obsolete_count;

	$this->comment($new_obsoletes . " new suggestions created.");

})->describe('Creates suggestions based on functional reprints and their existing suggestions');

Artisan::command('create-labels', function () {
	$obsoletes = Obsolete::with(['inferior', 'superior'])->get();

	foreach ($obsoletes as $obsolete) {
		$obsolete->labels = create_labels($obsolete->inferior, $obsolete->superior, $obsolete);
		$obsolete->save(['touch' => false]);
	}
})->describe('Re-creates labels for obsolete cards');

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

	$cards = Card::with(['superiors'])
		->whereNull('main_card_id')
		->whereDoesntHave('cardFaces')
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
			->where('id', "!=", $card->id)
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
	$bar->finish();
	$this->comment($count . " better cards found. " . (Obsolete::count() - $old_obsolete_count) . "  new records created for database. ");

})->describe('Populates obsoletes table with programmatically findable strictly better cards');


Artisan::command('download-scryfall', function () {

	$filename = 'scryfall-default-cards.json';

	$this->comment("Requesting bulkfile metadata...");

	$client = new \GuzzleHttp\Client();
	$request = $client->get('https://api.scryfall.com/bulk-data');

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

		if ($bulkfile["type"] != "default_cards")
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

		$client->get($bulkfile["permalink_uri"], ['sink' => $localfile]);

		$this->comment("Download complete");

		return 0;
		
	}
	$this->comment("Couldn't find correct bulk file type");
	return 1;

})->describe('Downloads newest card database from Scryfall');

Artisan::command('full-update', function () {

	$this->comment(date('[Y-m-d H:i:s]') . " Full update started");

	if (Artisan::call('download-scryfall', [], $this->getOutput()) !== 0)
		return;

	Artisan::call('load-scryfall', [], $this->getOutput());
	Artisan::call('populate-functional-reprints', [], $this->getOutput());
	Artisan::call('create-functional-obsoletes', [], $this->getOutput());
	Artisan::call('create-obsoletes', [], $this->getOutput());

})->describe('Performs full update cycle');
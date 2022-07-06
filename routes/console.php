<?php

use App\Card;
use App\Obsolete;
use App\FunctionalReprint;
use App\Cardtype;
use App\Labeling;
use App\Excerpt;
use App\Functionality;
use App\FunctionalityGroup;

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
					$card->timestamps = false;
					$card->functional_reprints_id = $group->id;
					$card->save();
					$card_count++;
				}
			}
		}
	});

	$new_count = FunctionalReprint::count();
	$results = $new_count - $count;

	// Delete any orphaned functional reprints
	FunctionalReprint::whereHas('cards', null, '<=', 1)->delete();

	$deleted = $new_count - FunctionalReprint::count();

	$this->comment($results . " new families created and " . $card_count . " cards added. " . $deleted . " orphaned families were deleted.");

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
		Labeling::with([
			'inferiors', 
			'superiors', 
			'obsolete'
		])->chunk(1000, function($labelings) {

			foreach ($labelings as $labeling) {
				$labeling->timestamps = false;
				$labeling->labels = create_labels($labeling->inferiors->first(), $labeling->superiors->first(), $labeling->obsolete);
				$labeling->save(['touch' => false]);
			}
		});
	});
})->describe('Re-creates labels for obsolete cards');

Artisan::command('remove-bad-suggestions', function () {

	$this->comment("Removing bad suggestions...");
	$count = 0;

	DB::transaction(function () use (&$count) {

		Obsolete::with(['inferiors', 'superiors'])->chunkById(1000, function($obsoletes) use (&$count) {
	
			foreach ($obsoletes as $obsolete) {
				if (!$obsolete->superiors->first()->isEqualOrBetterThan($obsolete->inferiors->first())) {

					$this->comment("Removing suggestion family: " . $obsolete->inferiors->first()->name . " -> " . $obsolete->superiors->first()->name);

					$obsolete->delete();
					$count++;
				}
			}
		});
	});
	$this->comment("Removed " . $count . " bad suggestions.");

})->describe('Removes suggestions that no longer pass inferior-superior check');

Artisan::command('recreate-substitute-rules', function () {

	$this->comment("Updating cards...");
	$count = 0;

	$q = Card::query();
	$bar = $this->output->createProgressBar($q->count());

	DB::transaction(function () use ($q, $bar, &$count) {
		$q->chunk(1000, function($cards) use ($bar, &$count) {
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
		
	});
	$bar->finish();
	$this->comment("Updated " . $count . " cards with new rule substitutions.");

})->describe('Re-creates substitued rules for existing cards.');

Artisan::command('create-obsoletes', function () {

	$this->comment("Looking for better cards...");

	$bar = null;
	$count = 0;
	$progress_callback = function($cardcount, $at, $card = null, $betters = []) use (&$bar) {

		if (!$bar) {
			$bar = $this->output->createProgressBar($cardcount);
			$bar->start();
		}

		if ($at > 0 && $at < $cardcount)
			$bar->setProgress($at);

		else if ($at >= $cardcount)
			$bar->finish();

		/*
		foreach ($betters as $better) {
			$this->comment("#" . $card->id . " " . $card->name . " -> #" . $better->id . " " . $better->name);
		}
		*/
	};

	$old_obsolete_count = App\Obsolete::count();

	create_obsoletes($count, false, $progress_callback);
	
	$this->comment($count . " better cards found. " . (Obsolete::count() - $old_obsolete_count) . "  new records created for database. ");

})->describe('Populates obsoletes table with programmatically findable strictly better cards');

Artisan::command('create-obsoletes-by-analysis', function () {

	$this->comment("Looking for better cards by analysis...");

	$bar = null;
	$count = 0;
	$progress_callback = function($cardcount, $at, $card = null, $betters = []) use (&$bar) {

		if (!$bar) {
			$bar = $this->output->createProgressBar($cardcount);
			$bar->start();
		}

		if ($at > 0 && $at < $cardcount)
			$bar->setProgress($at);

		else if ($at >= $cardcount)
			$bar->finish();
		
		/*
		foreach ($betters as $better) {
			$this->comment("#" . $card->id . " " . $card->name . " -> #" . $better->id . " " . $better->name);
		}
		*/
	};

	$old_obsolete_count = App\Obsolete::count();

	create_obsoletes($count, true, $progress_callback);
	
	$this->comment($count . " better cards found. " . (Obsolete::count() - $old_obsolete_count) . "  new records created for database. ");

})->describe('Populates obsoletes table with cards that are better by previous rule analysis');


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

Artisan::command('regroup-cards', function () {

	$this->comment("Regrouping cards...");

	{
		$duplicate_groups = DB::table(app(FunctionalityGroup::class)->getTable())
			->selectRaw('count(id) as duplicates, hash')
	        ->groupBy('hash')
	        ->having('duplicates', '>', 1)
	        ->get();

	    if ($duplicate_groups->count() > 0)
	    	$this->comment("Combining " . $duplicate_groups->count() . " duplicate groups...");

	    foreach ($duplicate_groups as $group) {
	    	$duplicates = FunctionalityGroup::with('functionalities')->where('hash', $group->hash)->orderBy('id', 'asc')->get();
	    	$first = $duplicates->first();
	    	foreach ($duplicates as $duplicate) {

	    		if ($first->id === $duplicate->id)
	    			continue;

	    		$duplicate->migrateTo($first);

	    		foreach ($duplicate->functionalities as $functionality) {
	    			$functionality->group_id = $first->id;
	    			$functionality->save();
	    		}
	    		$duplicate->delete();
	    	}
	    }

	    $duplicate_functionalities = DB::table(app(Functionality::class)->getTable())
			->selectRaw('count(id) as duplicates, hash')
	        ->groupBy('hash')
	        ->having('duplicates', '>', 1)
	        ->get();

	    if ($duplicate_functionalities->count() > 0)
	    	$this->comment("Combining " . $duplicate_functionalities->count() . " duplicate functionalities...");

	    foreach ($duplicate_functionalities as $functionality) {
	    	$duplicates = Functionality::with(['group', 'cards'])->where('hash', $functionality->hash)->orderBy('id', 'asc')->get();
	    	$first = $duplicates->first();
	    	foreach ($duplicates as $duplicate) {

	    		if ($first->id === $duplicate->id)
	    			continue;

	    		$duplicate->migrateTo($first, $first->group);

	    		foreach ($duplicate->cards as $card) {
	    			$card->timestamps = false;
	    			$card->functionality_id = $first->id;
	    			$card->save();
	    		}
	    		$duplicate->delete();
	    	}
	    }
	}

	{
		$this->comment("Linking cards to functionalities and groups...");

		$q = Card::with(['functionality.group'])->whereNull('main_card_id');
		$bar = $this->output->createProgressBar($q->count());

		DB::transaction(function () use ($q, $bar) {
			$q->chunkById(1000, function($cards) use ($bar) {
				foreach ($cards as $card) {
					$card->timestamps = false;
					$card->linkToFunctionality();
					$bar->advance();
				}
			});
		});

		$bar->finish();
	}

	{
		$this->comment("Removing invalid obsolete <=> labeling relations...");
		$count = Labeling::count();

		// Delete invalid obsolete<->labeling relations
		Obsolete::with(['labelings.superior', 'labelings.inferior'])->chunkById(1000, function($obsoletes) {

			foreach ($obsoletes as $obsolete) {

				// Remove better-worse relations from type variants
				if ($obsolete->superior_functionality_group_id === $obsolete->inferior_functionality_group_id) {
					$obsolete->delete();
					continue;
				}

				foreach ($obsolete->labelings as $labeling) {
					if ($labeling->superior->group_id !== $obsolete->superior_functionality_group_id || 
						$labeling->inferior->group_id !== $obsolete->inferior_functionality_group_id)
						$labeling->delete();
				}
			}
		});
		$removed_count = $count - Labeling::count();
		$this->comment("Removed ".$removed_count." invalid obsolete <=> labeling relations.");
	}

	Artisan::call('relabel-obsoletes', [], $this->getOutput());
});

/*
 This command goes through existing obsoletes and creates missing labelings for any functionalities related to functionality groups referred in the Obsolete
 This doesn't happen in normal runtime, sometimes things break.
 */
Artisan::command('relabel-obsoletes', function () {

	$q = Obsolete::with(['inferiors', 'superiors', 'labelings'])
		->whereRaw('inferior_functionality_group_id != superior_functionality_group_id');

	$labeling_count = Labeling::count();

	$bar = $this->output->createProgressBar($q->count());

	DB::transaction(function () use ($q, $bar) {
		$q->chunk(1000, function($obsoletes) use ($bar) {
			foreach ($obsoletes as $obsolete) {
				
				foreach ($obsolete->inferiors as $inferior) {
					foreach ($obsolete->superiors as $superior) {
						if ($inferior->functionality_id != $superior->functionality_id) {
							if ($obsolete->labelings
								->where('inferior_functionality_id', $inferior->functionality_id)
								->where('superior_functionality_id', $superior->functionality_id)
								->count() == 0)
								create_labeling($inferior, $superior, $obsolete, false);
						}
					}
				}
				$bar->advance();
			}
		});
	});

	$bar->finish();

	$new_labeling_count = Labeling::count();
	$this->comment("Created " . ($new_labeling_count - $labeling_count) . " new labelings");
});

Artisan::command('filter-external-suggestions', function () {

	App\Suggestion::chunkById(1000, function($suggestions) {

		foreach ($suggestions as $suggestion) {

			$inferior_name = $suggestion->inferiors[0];

			$inferior = App\Card::with(['superiors'])->where('name', $inferior_name)->whereNull('main_card_id')->first();
			$superiors = App\Card::whereIn('name', $suggestion->superiors)->whereNull('main_card_id')->get();

			if (!$inferior || $superiors->isEmpty())  {
				$this->comment("Couldn't find inferior: ". $inferior_name ." or its superiors: ". $suggestion->Superior);
				$suggestion->delete();
				continue;
			}

			// Check if we already have this suggestion
			$new_to_db = $superiors->filter(function($superior) use ($inferior) {
				return !$inferior->superiors->contains('id', $superior->id);
			});

			// Check if our rules allow this suggestion
			$new_to_db = $new_to_db->filter(function($superior) use ($inferior) {
				if ($superior->isEqualOrBetterThan($inferior))
					return true;

				$this->comment("Rules don't allow suggestion: ". $inferior->name ." -> ". $superior->name);
				return false;
			});

			$suggestion->saveSuperiors($new_to_db->pluck('name')->all());
		}
	});
});

Artisan::command('analyze-rules', function () {

	// Clear previous analyzes
	// This will keep positivty and negativity points relevant and also clear any previous misjudgements
	Excerpt::query()->delete();
	DB::statement('ALTER TABLE excerpts AUTO_INCREMENT = 1;');

	$new_excerpts = 0;

	$this->comment("Creating positive/negative excerpts from current suggestions...");

	$q = Obsolete::with(['inferiors', 'superiors'])
		->whereRaw('inferior_functionality_group_id != superior_functionality_group_id')
		->where('upvotes', '>', 5)->whereRaw('downvotes / upvotes < 0.3');

	$bar = $this->output->createProgressBar($q->count());

	$q->chunk(1000, function($obsoletes) use (&$new_excerpts, $bar) {

		foreach ($obsoletes as $obsolete) {
			$superior = $obsolete->superiors->first();
			$inferior = $obsolete->inferiors->first();
			
			// If inferior or superior is a multifaced card, 
			// search for the actual face that is better
			$face_found = (count($superior->cardFaces) == 0) && (count($inferior->cardFaces) == 0);
			if (!$face_found) {
				foreach ($superior->cardFaces as $sup) {

					foreach($inferior->cardFaces as $inf) {
						if ($sup->isEqualOrBetterThan($inf)) {
							$superior = $sup;
							$inferior = $inf;
							$face_found = true;
							break 2;
						}
					}

					if ($sup->isEqualOrBetterThan($inferior)) {
						$superior = $sup;
						$face_found = true;
					}
				}
				if (!$face_found) {
					foreach($inferior->cardFaces as $inf) {
						if ($superior->isEqualOrBetterThan($inf)) {
							$inferior = $inf;
							$face_found = true;
							break;
						}
					}
					if (!$face_found)
						continue;
				}
			}
			

			$excerpts = Excerpt::getNewExcerpts($inferior, $superior);
			foreach ($excerpts as $e) {

				$existing = Excerpt::where('text', $e->text)->first();
				if ($existing) {

					// Update ratings. 
					// Point system attempts to verify we haven't made misjudgements about rule being positive/negative
					$existing->positivity_points += $e->positivity_points;
					$existing->negativity_points += $e->negativity_points;
					$existing->positive = $existing->positivity_points == $existing->negativity_points ? null : ($existing->positivity_points > $existing->negativity_points);
					$existing->save();
					$existing->groups()->syncWithoutDetaching([$e->positive ? $obsolete->superior_functionality_group_id : $obsolete->inferior_functionality_group_id]);
				}
				else {
					$e->push();

					$id = $e->positive ? $obsolete->superior_functionality_group_id : $obsolete->inferior_functionality_group_id;
					$e->groups()->syncWithoutDetaching($id);
					$new_excerpts++;
				}
			}
			$bar->advance();
		}
	});

	$bar->finish();

	$this->comment("Analyzed " . ($new_excerpts) . " new excerpts from current suggestions");

	$new_excerpts = 0;
	$this->comment("Creating excerpts from cards...");

	$q = FunctionalityGroup::with(['examplecard']);

	$bar = $this->output->createProgressBar($q->count());

	$q->chunk(1000, function($groups) use (&$new_excerpts, $bar) {
	
		foreach ($groups as $group) {
			$raws = Excerpt::cardToRawExcerpts($group->examplecard);
			foreach ($raws as $text) {

				$excerpt = Excerpt::firstOrCreate(['text' => $text], ['regex' => 1]);
				$excerpt->groups()->syncWithoutDetaching($group->id);

				if ($excerpt->wasRecentlyCreated) {
					//ExcerptVariable::getVariablesFromText($text);
					$new_excerpts++;
				}
			}
			$bar->advance();
		}
	});
	$bar->finish();

	$this->comment("Created " . ($new_excerpts) . " new excerpts for cards");
})->describe('Trains AI about rules text betterness using highly voted suggestions');;

Artisan::command('full-update', function () {

	$this->comment(date('[Y-m-d H:i:s]') . " Full update started");

	if (Artisan::call('download-scryfall', [], $this->getOutput()) !== 0)
		return;

	Artisan::call('download-typedata', [], $this->getOutput());
	Artisan::call('load-scryfall', [], $this->getOutput());
	Artisan::call('populate-functional-reprints', [], $this->getOutput());
	Artisan::call('create-obsoletes', [], $this->getOutput());

})->describe('Performs full update cycle');
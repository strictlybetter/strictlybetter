<?php

use App\Card;
use App\Obsolete;
use App\FunctionalReprint;
use App\Cardtype;
use App\Labeling;
use App\Excerpt;
use App\ExcerptComparison;
use App\ExcerptVariable;
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

	$q = Functionality::with('cards')->has('cards', '>', 1);
	$this->comment($q->count() . " duplicate families found. Populating...");

	$count = FunctionalReprint::count();
	$card_count = 0;

	$old_ids = FunctionalReprint::orderBy('id')->pluck('id', 'id');

	DB::transaction(function () use ($q, &$old_ids, &$card_count) {

		$q->chunk(1000, function($results) use (&$old_ids, &$card_count) {		
			foreach ($results as $reprint_group) {

				//$sample = $reprint_group[0];
				$sample = $reprint_group->cards->first();

				$group = FunctionalReprint::FirstOrCreate([
					'typeline' => $sample->typeline,
					'manacost' => $sample->manacost, 
					'power' => $sample->power, 
					'toughness' => $sample->toughness, 
					'loyalty' => $sample->loyalty, 
					'rules' => $sample->substituted_rules,
				]);

				unset($old_ids[$group->id]);

				//$group->cards()->associate($reprint_group->pluck('id'));
				foreach ($reprint_group->cards as $card) {
					if ($card->functional_reprints_id != $group->id) {
						$card->timestamps = false;
						$card->functional_reprints_id = $group->id;
						$card->save();
						$card_count++;
					}
				}
			}
		});
	});

	$new_count = FunctionalReprint::count();
	$results = $new_count - $count;

	if (count($old_ids) > 0) {
		$this->comment("left ids: " . implode(", ", $old_ids->keys()->toArray()));
		FunctionalReprint::whereIn('id', $old_ids->keys()->toArray())->delete();
	}

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
		
		
		foreach ($betters as $better) {
			$this->comment("#" . $card->id . " " . $card->name . " -> #" . $better->id . " " . $better->name);
		}
		
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

	$round = 0;
	$positivity_shift_count = 0;

	do {

	$new_excerpts = 0;
	$new_variables = 0;
	$positivity_shift_count = 0;
	$positivity_shifts = ['excerpt' => [], 'variable' => []];
	$round++;

	$this->comment("Creating positive/negative excerpts from current suggestions (round ".$round.")...");

	$q = Obsolete::with([
			'inferiors.functionality.excerpts.variables', 'inferiors.cardFaces.functionality.excerpts.variables', 'inferiors.functionality.excerpts.superiors.variables',
			'superiors.functionality.excerpts.variables', 'superiors.cardFaces.functionality.excerpts.variables', 'superiors.functionality.excerpts.inferiors.variables',
			'inferiors.functionality.variablevalues', 
			'superiors.functionality.variablevalues',
		])
		->whereRaw('inferior_functionality_group_id != superior_functionality_group_id')
		->where('upvotes', '>=', 3)->whereRaw('downvotes / upvotes < 0.45');

	$bar = $this->output->createProgressBar($q->count());

	$q->chunk(1000, function($obsoletes) use (&$new_excerpts, &$new_variables, &$positivity_shifts, $bar, $round) {

		foreach ($obsoletes as $obsolete) {
			$superior = $obsolete->superiors->first();
			$inferior = $obsolete->inferiors->first();
			
			if ($superior->cardFaces->count() > 0 || $inferior->cardFaces->count() > 0)
				continue;
			/*if (!Card::findComparedFaces($superior, $inferior))
				continue;
			*/

			$superior_excerpt = null;
			$inferior_excerpt = null;

			$excerpts = Excerpt::getNewExcerpts($inferior, $superior, $round);
			foreach ($excerpts as $e) {

				$has_inferiors = !$e->inferiors->isEmpty();
				$has_superiors = !$e->superiors->isEmpty();

				// Can't push these relations before they exist, so unset for now
				// We will find them again with $inferior_id / $superior_id
				unset($e['superiors']);
				unset($e['inferiors']);

				$existing = Excerpt::with(['variables'])->where('text', $e->text)->first();
				if ($existing) {

					// Update ratings. 
					// Point system attempts to verify we haven't made misjudgements about rule being positive/negative
					$old_value = $existing->positive;
					$existing->sumPoints($e)->save();

					// If value was changed, we should re-iterate previous findings
					if ($old_value !== $existing->positive) {
						if (array_key_exists($existing->id, $positivity_shifts['excerpt']) && $positivity_shifts['excerpt'][$existing->id] === $existing->positive)
							unset($positivity_shifts['excerpt'][$existing->id]);
						else {
							$positivity_shifts['excerpt'][$existing->id] = $old_value;
						}
					}

					// Save variables
					foreach ($e->variables as $variable) {

						$i = $existing->variables->search(function($item, $key) use ($variable) { return $item->isSameVariable($variable); });
						$old_value = $existing->variables[$i]->positive;
						$existing->variables[$i]->sumPoints($variable)->save();
						if ($old_value !== $existing->variables[$i]->positive) {

							$id = $existing->variables[$i]->id;

							if (array_key_exists($id, $positivity_shifts['variable']) && $positivity_shifts['variable'][$id] === $existing->variables[$i]->positive)
								unset($positivity_shifts['variable'][$id]);
							else
								$positivity_shifts['variable'][$id] = $old_value;
						}

						// Remember value for later
						$existing->variables[$i]->setRuntimeValue($variable->getRuntimeValue());
					}
				}
				else {

					$e->push();
					$new_excerpts++;
					$new_variables += $e->variables->count();

					$e->variables()->saveMany($e->variables);
					$existing = $e;
				}
	
				if ($has_superiors)
					$inferior_excerpt = $existing;
				if ($has_inferiors)
					$superior_excerpt = $existing;
			}

			if ($superior_excerpt !== null && $inferior_excerpt !== null) {

				$comparison = ExcerptComparison::firstOrCreate([
						'inferior_excerpt_id' => $inferior_excerpt->id, 
						'superior_excerpt_id' => $superior_excerpt->id
					],
					[
						'reliability_points' => 1
					]
				);

				if (!$comparison->wasRecentlyCreated) {
					$comparison->reliability_points += 1;
					$comparison->save();
				}

				$variable_comparisons = collect([]);

				$superior_variables = $superior_excerpt->variables;
				$inferior_variables = $inferior_excerpt->variables;

				// Load variables to get their ids
		//		$superior_excerpt->load('variables');
		//		$inferior_excerpt->load('variables');

				//$previous = [];

				// Cross-compare all same type variables of these two (different) excerpts
				foreach ($superior_variables as $variable) {
					foreach ($inferior_variables as $inferior_variable) {

						if ($variable->isSameType($inferior_variable)) {

							// Find ids for these variables
							$tmpvar = $superior_excerpt->variables->first(function ($item, $key) use ($variable) { return $item->isSameVariable($variable); });
							$variable->id = $tmpvar->id;

							$tmpvar = $inferior_excerpt->variables->first(function ($item, $key) use ($inferior_variable) { return $item->isSameVariable($inferior_variable); });
							$inferior_variable->id = $tmpvar->id;

							/*
							// skip any variable pair we already compared the other way around
							if (isset($previous[$variable->capture_type][$inferior_variable->capture_id][$variable->capture_id]))
								continue;

							$previous[$variable->capture_type][$variable->capture_id][$inferior_variable->capture_id] = 1;
							*/
						
							$variable_comparisons->push(ExcerptVariable::createComparison($variable, $inferior_variable));
						}
					}
				}
				if (!$variable_comparisons->isEmpty())
					$comparison->variablecomparisons()->saveMany($variable_comparisons);

			}

			$bar->advance();
		}
	});

	$bar->finish();

	$positivity_shift_count = count($positivity_shifts['excerpt']) + count($positivity_shifts['variable']);

	if ($round > 1)
		var_dump($positivity_shifts);

	$this->comment("Analyzed " . $new_excerpts . " new excerpts with " . $new_variables . " new variables and " . $positivity_shift_count . " positivity shifts from current suggestions.");
	} while ($new_excerpts > 0 || $new_variables > 0 || $positivity_shift_count > 0);

	$new_excerpts = 0;
	$this->comment("Creating excerpts from cards...");

	$q = FunctionalityGroup::with(['examplecard']);

	$bar = $this->output->createProgressBar($q->count());

	$q->chunk(1000, function($groups) use (&$new_excerpts, $bar) {
	
		foreach ($groups as $group) {
			$excerpts = Excerpt::cardToExcerpts($group->examplecard);
			
			$variablevalues = collect([]);
			foreach ($excerpts as $excerpt) {

				$existing = Excerpt::with(['variables'])->firstOrCreate(['text' => $excerpt->text], $excerpt->getAttributes());
				$existing->groups()->syncWithoutDetaching($group->id);

				if ($existing->wasRecentlyCreated) {
					$new_excerpts++;
					if (!$excerpt->variables->isEmpty()) {
						$existing->variables()->saveMany($excerpt->variables);
						$existing->refresh();	// refresh to get variable ids later
					}
				}

				foreach ($excerpt->variables as $variable) {

					$id = $existing->variables->search(function($item, $key) use ($variable) { return $item->isSameVariable($variable); });

					$variablevalues->push(new App\ExcerptVariableValue([
						'value' => $variable->valueToJsonable(),
						'variable_id' => $existing->variables[$id]->id,
						'group_id' => $group->id
					]));
				}
			}
			$group->variablevalues()->saveMany($variablevalues);
			
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
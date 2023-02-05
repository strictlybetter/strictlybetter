<?php

namespace App\Http\Controllers;

use App\Card;
use Illuminate\Http\Request;

class DeckController extends Controller
{
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		return view('deck.index')->with(['tribelist' => make_tribe_list(false), 'formatlist' => make_format_list(), 'deck' => session('deck'), 'deckupgrades' => session('deckupgrades'), 'total' => session('total')]);
	}

	public function upgrade(Request $request)
	{
		$request->validate([
			'tribes' => 'array'
		]);

		$deck = $this->parseDeck($request->input('deck'));

		// Verify we parsed something
		if (count($deck) === 0) {
			return redirect()->route('deck.index')->with(['deck' => $deck, 'deckupgrades' => [], 'total' => 0])->withInput();
		}

		// Only pick 10 first tribes
		$tribes = array_slice($request->input('tribes', []), 0 , 10);
		$tribes = array_values(array_intersect($tribes, get_tribes()));

		$format = $request->input('format');
		if (!in_array($format, get_formats()))
			$format = "";

		$card_names = array_keys($deck);

		$cards = Card::select(['id', 'name', 'color_identity'])->whereNull('main_card_id')->where(function($q) use ($card_names) {
			$q->whereIn('name', $card_names)
			->orWhereHas('cardFaces', function($q) use ($card_names) { 
				$q->whereIn('name', $card_names);
			});
		})->get();

		$card_names = $cards->pluck('name');

		// Find colors the deck musn't contain (useful for Commander)
		$un_color_identity = [];
		if ($format === 'commander') {

			$deck_colors = $this->getDeckColors($cards);
			$un_color_identity = array_values(array_diff(["W","B","U","R","G"], $deck_colors));
		}

		$card_restrictions = function($q) use ($format, $un_color_identity, $card_names) {

			$q->relatedGuiOnly(['subtypes']);

			if ($format !== "")
				$q->where(function($q) use ($format) { $q->where('legalities->' . $format, 'legal')->orWhere('legalities->' . $format, 'restricted'); });

			// Don't suggest cards that are already in the deck
			$q->whereNotIn('name', $card_names);

			foreach ($un_color_identity as $un_color) {
				$q->whereJsonDoesntContain('color_identity', $un_color);
			}

			$q->orderBy('upvotes', 'desc');
		};

		$card_restrictions_typevariants = function($q) use ($format, $un_color_identity, $card_names) {

			$q->guiOnly(['subtypes']);

			if ($format !== "")
				$q->where(function($q) use ($format) { $q->where('legalities->' . $format, 'legal')->orWhere('legalities->' . $format, 'restricted'); });

			// Don't suggest cards that are already in the deck
			$q->whereNotIn('name', $card_names);

			foreach ($un_color_identity as $un_color) {
				$q->whereJsonDoesntContain('color_identity', $un_color);
			}
		};

		// Find replacements
		$upgrades = Card::guiOnly(['subtypes'])->with([
			'superiors' => $card_restrictions, 
			'inferiors' => $card_restrictions, 
			'functionality.typevariantcards' => $card_restrictions_typevariants
		])->whereIn('name', $card_names)
			->whereNull('main_card_id')
			->where(function($q) use ($card_restrictions, $card_restrictions_typevariants) {
				$q->whereHas('superiors', $card_restrictions)
				->orWhereHas('functionality.typevariantcards', $card_restrictions_typevariants);
			})
			
			->get();

		// Additional sorting if tribes are selected
		if (count($tribes) > 0) {
			foreach ($upgrades as $card) {

				// See if upgradable card belongs to the specified tribe
				// and if so, remove any suggestions for it that don't
				if (!empty(array_intersect($tribes, $card->subtypes))) {
					$card->superiors = $card->superiors->reject(function($superior) use ($tribes) {
						return empty(array_intersect($superior->subtypes, $tribes));
					})->values();
					$card->functionality->typevariantcards = $card->functionality->typevariantcards->reject(function($typevariant) use ($tribes) {
						return empty(array_intersect($typevariant->subtypes, $tribes));
					})->values();
				}

				// In case upgradable card doesn't belong to the specified tribe,
				// sort suggestions so cards of the specfied tribe come first (then by upvotes)
				else {
					$card->superiors = $card->superiors->sort(function($a, $b) use ($tribes) {

						$a_tribe = count(array_intersect($a->subtypes, $tribes));
						$b_tribe = count(array_intersect($b->subtypes, $tribes));

						$value = -($a_tribe <=> $b_tribe);	

						if ($value !== 0)
							return $value;

						return -($a->pivot->upvotes <=> $b->pivot->upvotes);

					})->values();

					$card->functionality->typevariantcards = $card->functionality->typevariantcards->sort(function($a, $b) use ($tribes) {
						$a_tribe = count(array_intersect($a->subtypes, $tribes));
						$b_tribe = count(array_intersect($b->subtypes, $tribes));

						return -($a_tribe <=> $b_tribe);	
					});
				}
			}

			// If no suggestions are left for a upgradable card, remove it from the list 
			$upgrades = $upgrades->reject(function($card) {
				return (count($card->superiors) == 0 && count($card->functionality->typevariantcards) == 0);
			})->values();
		}

		return redirect()->route('deck.index')->with(['deck' => $deck, 'deckupgrades' => $upgrades, 'total' => $cards->count()])->withInput();
    }

	public function getDeckColors($cards)
	{
		$deck_colors = [];
		foreach ($cards as $card) {
			$deck_colors = array_merge($deck_colors, array_values(array_diff($card->color_identity, $deck_colors))); 
		}
		return $deck_colors;
	}

	public function parseDeck($rawdeck) 
	{
		// We need to parse card name from each line which may be as follows:
		// 1x Llanowar Elves (CTD) *CMC:20* *EN*
		$pattern = '/^(?:(\d+)x? )?([^\/].*?)(?: \(\w*\)(?: \d+)?)?(?: \*.*\*)?$/';
		$deck = [];
		$count = 0;
		$card_limit = 10000;

		$separator = "\r\n";
		$line = strtok($rawdeck, $separator);

		while ($line !== false) {
			if (preg_match($pattern, $line, $match)) {

				if (count($match) === 3 && $match[2]) {

					$itemcount = ($match[1] && $match[1] > 0) ? $match[1] : 1;

					if (isset($deck[$match[2]]))
						$deck[$match[2]] += $itemcount;
					else
						$deck[$match[2]] = $itemcount;
				}
			}

			$count++;
			if ($count > $card_limit)
				break;

			$line = strtok($separator);
		}

		return $deck;
    }
}
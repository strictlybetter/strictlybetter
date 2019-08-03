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
		return view('deck.index')->with(['formatlist' => make_format_list(), 'deck' => session('deck'), 'deckupgrades' => session('deckupgrades')]);
	}

	public function upgrade(Request $request)
	{
		$deck = $this->parseDeck($request->input('deck'));

		// Verify we parsed something
		if (count($deck) === 0) {
			return redirect()->route('upgradedDeck')->with(['deck' => $deck, 'deckupgrades' => []]);
		}

		$format = $request->input('format');
		if (!in_array($format, Card::$formats))
			$format = "";

		$cards = Card::whereIn('name', array_keys($deck))->whereNull('main_card_id')->get();

		// Find colors the deck musn't contain (useful for Commander)
		$un_color_identity = [];
		if ($format === 'commander') {

			$deck_colors = $this->getDeckColors($cards);
			$un_color_identity = array_diff(["W","B","U","R","G"], $deck_colors);
		}

		$card_restrictions = function($q) use ($format, $un_color_identity, $cards) {

			if ($format !== "")
				$q->where('legalities->' . $format, 'legal');

			$q->whereNotIn('name', $cards->pluck('name'));

			foreach ($un_color_identity as $un_color) {
				$q->whereJsonDoesntContain('color_identity', $un_color);
			}

			$q->orderBy('upvotes', 'desc');
		};

		// Find replacements
		$upgrades = Card::with(['superiors' => $card_restrictions])->whereIn('name', array_keys($deck))->whereNull('main_card_id')->whereHas('superiors', $card_restrictions)->get();

		return redirect()->route('deck.index')->with(['deck' => $deck, 'deckupgrades' => $upgrades])->withInput();
    }

	public function getDeckColors($cards)
	{
		$deck_colors = [];
		foreach ($cards as $card) {
			$deck_colors = array_merge($deck_colors, array_diff($card->color_identity, $deck_colors)); 
		}
		return $deck_colors;
	}

	public function parseDeck($rawdeck) 
	{
		// We need to parse card name from each line which may be as follows:
		// 1x Llanowar Elves (CTD) *CMC:20* *EN*
		$pattern = '/^(?:(\d+)x? )?([^\/]+?)(?: \(\w*\)(?: \d+)?)?(?: \*.*\*)?$/';
		$deck = [];

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
			$line = strtok($separator);
		}

		return $deck;
    }
}
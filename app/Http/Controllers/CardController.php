<?php

namespace App\Http\Controllers;

use App\Card;
use App\Obsolete;
use App\Suggestion;
use Illuminate\Http\Request;

use mtgsdk\Card as CardApi;

class CardController extends Controller
{

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		$cards = Card::with(['superiors'])->whereHas('superiors')->orderBy('updated_at', 'desc')->paginate(10);
		//$cards = Obsolete::with(['superior', 'inferior'])->orderBy('created_at', 'desc')->paginate(10);

	    return view('index')->with(['cards' => $cards]);
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create(Card $card = null)
	{
		$inferiors = [];
		$superiors = [];

		if ($card) {
			$inferiors = [$card->name => $card->name];
			$superiors = $card->superiors()->pluck('name', 'name');
		}

	    return view('card.create')->with(['card' => $card, 'inferiors' => $inferiors, 'superiors' => $superiors]);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request)
	{
		$request->validate([
			'inferior' => 'required|max:191|min:3',
			'superior' => 'required|max:191|min:3|different:inferior',
		]);

		$inferior = Card::where('name', $request->input('inferior'))->first();
		$superior = Card::where('name', $request->input('superior'))->first();

		if (!$inferior || !$superior)
			return back()->withErrors(['Card not found'])->withInput();

		if ($inferior->name == $superior->name)
			return redirect()->route('card.create', $inferior->id)->withErrors(['Inferior and superior cards must be different'])->withInput();

		if (!$superior->isSuperior($inferior))
			return redirect()->route('card.create', $inferior->id)->withErrors(['Selected superior card does not seem to be strictly-better'])->withInput();

		if (!$inferior->exists)
			$inferior->save();
		else
			$inferior->touch();	// Touch inferior, so we can easily show recent updates on 'Browse' page

		if (!$superior->exists)
			$superior->save();
		else
			$superior->touch();

		$superior->inferiors()->syncWithoutDetaching([$inferior->id]);

		// Add vote
		$obsolete = Obsolete::where('superior_card_id', $superior->id)->where('inferior_card_id', $inferior->id)->first();
		$this->addVote($obsolete, true);

	    return redirect()->route('card.create', $inferior->id)->withInput();
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  \App\Card  $card
	 * @return \Illuminate\Http\Response
	 */
	public function show(Card $card)
	{
		$card->load(['superiors']);
		return view('card.show')->with(['card' => $card]);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  \App\Card  $card
	 * @return \Illuminate\Http\Response
	 */
	public function destroy(Card $card)
	{
	    //
	}

	public function getCard($cardname)
	{
		// Try local DB first
		$card = Card::where('name', $cardname)->first();

		if ($card)
			return $card;

		// No? Then query API
		$fetched_card = $this->queryCardApi($cardname);

		if (!$fetched_card)
			return null;

		// Make the card entry into a Card object we can save locally
		return new Card([
			'name' => $fetched_card['name'],
			'multiverse_id' => $fetched_card['multiverseid'],
			'legalities' => $fetched_card['legalities'],
			'price' => 0
		]);
	}

	protected function queryCardApi($cardname)
	{
		// Perform query
		$api_request = CardApi::where(['name' => $cardname, 'page' => 1])->array();

		// Find a card entry with valid data
		$fetched_card = null;
		foreach ($api_request as $item)
		{
			if (isset($item['multiverseid']) && $item['multiverseid'] && $item['name'] == $cardname) {
				return $item;
			}
		}
		return null;
	}

	public function cardAutocomplete(Request $request)
	{
		$request->validate([
			'term' => 'required|max:191|min:2'
		]);

		$term = escapeLink($request->input('term'));
		$cards = Card::where('name', 'like', $term.'%')->orderBy('name')->paginate(25)->pluck('name');

		// Additional formatting for select2 requests
		if ($request->input('select2')) {
			foreach ($cards as $i => $card) {
				$cards[$i] = [
					'id' => $card,
					'text' => $card,
					'gathererUrl' => "https://gatherer.wizards.com/Handlers/Image.ashx?multiverseid=25646&type=card"
				];
			}
		}

		return response()->json($cards);
	}

	public function search(Request $request) 
	{
		$request->validate([
			'term' => 'required|max:191|min:2'
		]);

		$term = escapeLike($request->input('term'));
		$card = Card::where('name', 'like', $term.'%')->first();

		if (!$card)
			return back()->withErrors(['No results for card: '. $request->input('term')])->withInput();

		return redirect()->route('card.create', $card->id);
	}

	public function upvote(Obsolete $obsolete)
	{
		$this->addVote($obsolete, true);
		return response()->json(['upvotes' => $obsolete->upvotes, 'downvotes' => $obsolete->downvotes]);
	}

	public function downvote(Obsolete $obsolete)
	{
		$this->addVote($obsolete, false);
		return response()->json(['upvotes' => $obsolete->upvotes, 'downvotes' => $obsolete->downvotes]);
	}

	protected function addVote(Obsolete $obsolete, $upvote) 
	{
		$ip = $this->request->ip();
		$previous_vote = Suggestion::where('obsolete_id', $obsolete->id)->where('ip', $ip)->first();

		if ($previous_vote) {
			if ($previous_vote->upvote == $upvote)
				return false;
			else {
				$previous_vote->upvote = $upvote;
				$previous_vote->save();

				if ($upvote)
					$obsolete->downvotes--;
				else
					$obsolete->upvotes--;
			}
		}
		else {

			$obsolete->suggestions()->create([
				'obsolete_id' => $obsolete->id,
				'ip' => $ip,
				'upvote' => $upvote
			]);
		}

		if ($upvote)
			$obsolete->upvotes++;
		else
			$obsolete->downvotes++;

		$obsolete->save();

		return true;
	}
}
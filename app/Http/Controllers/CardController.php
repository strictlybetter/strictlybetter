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
	public function index(Request $request)
	{
		$term = $request->input('search', '');
		if ($term === null) $term = "";

		$formatlist = make_format_list();

		$format = $request->input('format');
		if (!in_array($format, Card::$formats))
			$format = "";

		$page = $request->input('page', 1);
		if ($page === null) $page = 1;

		//$cards = $this->browse($request, $format, $term, "updated_at");

	    return view('index')->with(['search' => $term, 'format' => $format, 'page' => $page, 'formatlist' => $formatlist]);
	}

	public function quicksearch(Request $request)
	{
		$term = $request->input('search', '');
		if ($term === null) $term = "";

		$format = $request->input('format');
		if (!in_array($format, Card::$formats))
			$format = "";

		$cards = $this->browse($request, $format, $term, "updated_at");

		return view('card.partials.browse')->with(['cards' => $cards, 'search' => $term, 'format' => $format]);
	}

	protected function browse(Request $request, $format = '', $term = '', $orderBy = 'name')
	{

		$card_filters = function($q) use ($format) {

			if ($format !== "")
				$q->where('legalities->' . $format, 'legal');
		};

		$cards = Card::with(['superiors' => $card_filters, 'inferiors' => $card_filters, 'functionalReprints' => $card_filters]);

		// Apply search term if any
		if ($term !== "") {
			$cards = $cards->where('name', 'like', escapeLike($term).'%');
		}

		// We might not need to filter the inferior cards through formats
		if ($format !== "" && $term === "") {
			$cards = $cards->where('legalities->' . $format, 'legal');
		}

		$cards = $cards->orderBy($orderBy, 'desc')->paginate(10);

		// Remove self from functional reprints
		foreach ($cards as $i => $card) {
			if (count($card->functionalReprints) > 0) {
				$cards[$i]->functionalReprints = $card->functionalReprints->reject(function($item) use ($card) {
					return ($card->id === $item->id);
				})->values();
			}
		}

		$cards->setPath(route('index', ['search' => $term, 'format' => $format]));

		return $cards;
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
			$card->load(['functionalReprints', 'superiors']);

			$inferiors = $card->functionalReprints;
			if ($inferiors->isEmpty())
				$inferiors->push($card);

			$inferiors = $this->convertToSelect2Data($inferiors, $card);
			$superiors = $this->convertToSelect2Data($card->superiors);

			// Remove self from reprints
			$card->functionalReprints = $card->functionalReprints->reject(function($item) use ($card) {
				return ($card->id === $item->id);
			})->values();
		}

		return view('card.create')->with(['card' => $card, 'inferiors' => $inferiors, 'superiors' => $superiors]);
	}

	public function upgradeview(Card $card)
	{
		$card->load(['functionalReprints', 'superiors']);

		// Remove self from reprints
		$card->functionalReprints = $card->functionalReprints->reject(function($item) use ($card) {
			return ($card->id === $item->id);
		})->values();

	    return view('card.partials.upgrade')->with(['card' => $card]);
	}

	protected function convertToSelect2Data($cards, Card $selected_card = null)
	{
		$list = [];
		foreach ($cards as $card) {
			$item = [
				'text' => $card->name,
				'id' => $card->id,
				'imageUrl' => $card->imageUrl,
				'typeline' => $card->typeLine
			];

			if ($selected_card && $card->id == $selected_card->id)
				$item['selected'] = true;

			$list[] = $item;
		}
		return $list;
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
			'inferior' => 'required',
			'superior' => 'required|different:inferior',
		]);

		$inferior = Card::with('functionalReprints')->where('id', $request->input('inferior'))->first();
		$superior = Card::with('functionalReprints')->where('id', $request->input('superior'))->first();

		if (!$inferior || !$superior)
			return back()->withErrors(['Card not found'])->withInput();

		if ($inferior->name == $superior->name)
			return redirect()->route('card.create', $inferior->id)->withErrors(['The cards must be different'])->withInput();

		if (!$superior->isSuperior($inferior))
			return redirect()->route('card.create', $inferior->id)->withErrors(['The strictly better card does not seem to fill the requirements of the term'])->withInput();

		$inferior->touch();	// Touch inferior, so we can easily show recent updates on 'Browse' page
		$superior->touch();

		create_obsolete($inferior, $superior, true);

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

	public function cardAutocomplete(Request $request)
	{
		$request->validate([
			'term' => 'required|max:191|min:2'
		]);

		$term = escapeLike($request->input('term'));
		$cards = Card::where('name', 'like', $term.'%')->orderBy('name')->paginate(25);

		$list = [];

		// Additional formatting for select2 requests
		if ($request->input('select2'))
			$list = $this->convertToSelect2Data($cards);
		else 
			$list = $cards->pluck('name');

		return response()->json($list);
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

		if ($this->request->ajax())
			return response()->json(['upvotes' => $obsolete->upvotes, 'downvotes' => $obsolete->downvotes]);
		else
			return back()->withInput();
	}

	public function downvote(Obsolete $obsolete)
	{
		$this->addVote($obsolete, false);

		if ($this->request->ajax())
			return response()->json(['upvotes' => $obsolete->upvotes, 'downvotes' => $obsolete->downvotes]);
		else
			return back()->withInput();
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
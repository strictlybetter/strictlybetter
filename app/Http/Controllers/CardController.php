<?php

namespace App\Http\Controllers;

use App\Card;
use App\Obsolete;
use App\Labeling;
use App\Vote;
use App\Cardtype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CardController extends Controller
{
	protected $card_orders = [
		'name' => ['card' => 'name', 'obsolete' => 'name', 'direction' => 'asc'], 
		'updated_at' => ['card' => 'updated_at', 'obsolete' => 'labelings.created_at', 'direction' => 'desc'], 
		'upvotes' => ['card' => 'updated_at', 'obsolete' => 'upvotes', 'direction' => 'desc']
	];

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request)
	{
		$request->validate([
			'search' => 'max:100'
		]);

		$term = $request->input('search', '');
		if ($term === null) $term = "";

		$tribelist = make_tribe_list();
		$tribe = $request->input('tribe');
		if (!in_array($tribe, array_keys($tribelist)))
			$tribe = "";

		$formatlist = make_format_list();

		$format = $request->input('format');
		if (!in_array($format, array_keys($formatlist)))
			$format = "";

		$page = $request->input('page', 1);
		if ($page === null) $page = 1;

		$filters = $request->input('filters');

		$filterlist = [];
		foreach (Labeling::$labellist as $label) {
			if ($label == "strictly_better")
				continue;
			$filterlist[$label] = \Lang::get('card.filters.' . $label);
		}

		$orderlist = make_select_options(array_keys($this->card_orders), 'orders');

		$order = $request->input('order');
		$order = isset($this->card_orders[$order]) ? $order : 'updated_at';

	    return view('index')->with([
	    	'search' => $term,
	    	'tribe' => $tribe, 
	    	'format' => $format, 
	    	'order' => $order,
	    	'page' => $page,
	    	'tribelist' => $tribelist, 
	    	'formatlist' => $formatlist, 
	    	'filters' => $filters,
	    	'filterlist' => $filterlist,
	    	'orderlist' => $orderlist
	    ]);
	}

	public function quicksearch(Request $request)
	{
		$request->validate([
			'search' => 'max:100'
		]);

		$term = $request->input('search', '');
		if ($term === null) $term = "";

		$tribe = $request->input('tribe');
		if (!in_array($tribe, get_tribes()))
			$tribe = "";

		$format = $request->input('format');
		if (!in_array($format, get_formats()))
			$format = "";

		$filters = $request->input('filters');

		$order = $request->input('order');
		$order = isset($this->card_orders[$order]) ? $order : 'updated_at';

		$cards = $this->browse($request, $tribe, $format, $term, $filters, $order);

		if ($cards->total() == 0)
			$cards = $this->browse($request, $tribe, $format, $term, $filters, $order, false);

		if ($inputs = $this->checkPaginationBounds($request, $cards)) 
			return redirect()->route('card.quicksearch', $inputs);

		return view('card.partials.browse')->with(['cards' => $cards, 'search' => $term, 'tribe' => $tribe, 'format' => $format, 'filters' => $filters, 'order' => $order]);
	}

	protected function checkPaginationBounds($request, $cards)
	{
		if ($cards->lastPage() > 0 && $cards->lastPage() < $request->input('page', 1)) {

			$inputs = $request->input();
			$inputs['page'] = $cards->lastPage();
			return $inputs;
		}
		return null;
	}

	protected function browse(Request $request, $tribe = '', $format = '', $term = '', $filters = [], $order_key = 'updated_at', $has_obsoletes = true)
	{
		$order = $this->card_orders[$order_key];

		$card_filters = function($q) use ($format, $tribe, $filters, $order) {

			$q->relatedGuiOnly();

			if ($format !== "")
				$q->where('legalities->' . $format, 'legal');

			if ($tribe !== "")
				$q->whereJsonContains('subtypes', $tribe);

			// Apply filters
			if (is_array($filters)) {
				foreach ($filters as $filter) {
					if (in_array($filter, Labeling::$labellist))
						$q->where('labelings.labels->' . $filter, false);
				}
			}

			$q->orderBy($order['obsolete'], $order['direction']);
		};

		$cards = Card::guiOnly()->with(['superiors' => $card_filters, 'inferiors' => $card_filters, 'functionalReprints' => function ($q) use ($format) {
			if ($format !== "")
				$q->where('legalities->' . $format, 'legal');
		}]);

		if ($has_obsoletes) {
			$cards = $cards->where(function($q) use ($card_filters, $term) {

				if ($term !== "")
					$q->where('name', $term)
					->orWhereHas('superiors', $card_filters)
					->orWhereHas('inferiors', $card_filters);
				else
					$q->whereHas('superiors', $card_filters);
				//	->orWhereHas('inferiors', $card_filters);
			});
		}

		// Apply search term if any
		if ($term !== "") {
			$cards = $cards->where('name', 'like', escapeLike($term).'%');
		}

		$orderBy = ($term == "") ? "updated_at" : "name";
		$direction = ($term == "") ? "desc" : "asc";

		$cards = $cards->whereNull('main_card_id')->orderBy($orderBy, $direction)->paginate(10);

		// Remove self from functional reprints
		foreach ($cards as $i => $card) {
			if (count($card->functionalReprints) > 0) {
				$cards[$i]->functionalReprints = $card->functionalReprints->reject(function($item) use ($card) {
					return ($card->id === $item->id);
				})->values();
			}
		}

		$cards->setPath(route('index', ['search' => $term, 'tribe' => $tribe, 'format' => $format, 'filters' => $filters, 'order' => $order_key]));

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

			if ($this->request->input('inferior')) {

				$superiors = $this->convertToSelect2Data([$card], $card);
				$card = null;
			}
			else {

				$card->load([
					'functionalReprints' => function($q) { $q->guiOnly(); }, 
					'superiors' => function($q) { $q->relatedGuiOnly(); }
				]);

				$inferiors = $card->functionalReprints;
				if ($inferiors->isEmpty())
					$inferiors->push($card);

				$inferiors = $this->convertToSelect2Data($inferiors, $card);
				//$superiors = $this->convertToSelect2Data($card->superiors);

				// Remove self from reprints
				$card->functionalReprints = $card->functionalReprints->reject(function($item) use ($card) {
					return ($card->id === $item->id);
				})->values();
			}
		}

		return view('card.create')->with(['card' => $card, 'inferiors' => $inferiors, 'superiors' => $superiors]);
	}

	public function upgradeview(Card $card)
	{
		$card->load([
			'functionalReprints' => function($q) { $q->guiOnly(); }, 
			'superiors' => function($q) { $q->relatedGuiOnly(); }]);

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
				'typeline' => $card->typeline
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

		$inferior = Card::with(['functionality'])->where('id', $request->input('inferior'))->whereNull('main_card_id')->first();
		$superior = Card::with(['functionality'])->where('id', $request->input('superior'))->whereNull('main_card_id')->first();

		if (!$inferior || !$superior)
			return back()->withErrors(['Card not found'])->withInput();

		if ($inferior->functionality->group_id == $superior->functionality->group_id)
			return redirect()->route('card.create', $inferior->id)->withErrors(['The cards belong to same functionality group'])->withInput();

		if (!$superior->isEqualOrBetterThan($inferior))
			return redirect()->route('card.create', $inferior->id)->withErrors(["The superior card doesn't seem to be better"])->withInput();

		DB::transaction(function () use ($inferior, $superior) {
			create_obsolete($inferior, $superior, true);

			// Add vote
			$obsolete = Obsolete::where('superior_functionality_group_id', $superior->functionality->group_id)
				->where('inferior_functionality_group_id', $inferior->functionality->group_id)
				->first();

			$this->addVote($obsolete->id, true);
		});

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
			'term' => 'required|max:191|min:2',
			'limit' => 'integer|min:1|max:50'
		]);

		$limit = $request->input('limit') ? $request->input('limit') : 25;
		$term = escapeLike($request->input('term'));
		$cards = Card::guiOnly()->where('name', 'like', $term.'%')->whereNull('main_card_id')->orderBy('name')->paginate($limit);

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
		$card = Card::select(['name'])->where('name', 'like', $term.'%')->whereNull('main_card_id')->first();

		if (!$card)
			return back()->withErrors(['No results for card: '. $request->input('term')])->withInput();

		return redirect()->route('index', ["search" => $card->name]);
	}

	public function upvote($obsolete_id)
	{
		$obsolete = $this->addVote($obsolete_id, true);

		if ($this->request->ajax())
			return response()->json(['upvotes' => $obsolete->upvotes, 'downvotes' => $obsolete->downvotes]);
		else
			return back()->withInput();
	}

	public function downvote($obsolete_id)
	{
		$obsolete = $this->addVote($obsolete_id, false);

		if ($this->request->ajax())
			return response()->json(['upvotes' => $obsolete->upvotes, 'downvotes' => $obsolete->downvotes]);
		else
			return back()->withInput();
	}

	protected function addVote($obsolete_id, $upvote) 
	{
		$ip = $this->request->ip();
		$obsolete = null;

		DB::transaction(function () use (&$obsolete, $obsolete_id, $upvote, $ip) {
			$obsolete = Obsolete::sharedLock()->findOrFail($obsolete_id);
			$previous_vote = Vote::where('obsolete_id', $obsolete->id)->where('ip', $ip)->first();

			if ($previous_vote) {
				if ($previous_vote->upvote == $upvote)
					return $obsolete;
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

				$obsolete->votes()->create([
					'obsolete_id' => $obsolete->id,
					'ip' => $ip,
					'upvote' => $upvote
				]);
			}

			if ($upvote)
				$obsolete->upvotes++;
			else
				$obsolete->downvotes++;

			$is_downvoted = (($obsolete->upvotes - $obsolete->downvotes) <= -10);

			$obsolete->labelings()->update(['labels->downvoted' => $is_downvoted]);

			$obsolete->save();
		});

		return $obsolete;
	}
}
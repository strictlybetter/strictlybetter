<?php

namespace App\Http\Controllers;

use App\Card;
use App\Obsolete;
use App\Labeling;
use App\Vote;
use App\Cardtype;
use App\Suggestion;
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

		$card_filters = function($q) use ($format, $tribe) {
			if ($format !== "")
				$q->where('legalities->' . $format, 'legal');

			if ($tribe !== "")
				$q->whereJsonContains('subtypes', $tribe);
		};

		$card_filters_related = function($q) use ($format, $tribe, $filters, $order, $card_filters) {

			$q->where($card_filters)->relatedGuiOnly();

			// Apply filters
			if (is_array($filters)) {
				foreach ($filters as $filter) {
					if (in_array($filter, Labeling::$labellist))
						$q->where('labelings.labels->' . $filter, false);
				}
			}
			$q->orderBy($order['obsolete'], $order['direction']);
		};

		$card_filters_other = function($q) use ($format, $tribe, $order, $card_filters) {
			$q->where($card_filters)->guiOnly();
		};

		$cards = Card::guiOnly()->with(['superiors' => $card_filters_related, 'inferiors' => $card_filters_related, 'functionality.similiarcards' => $card_filters_other, 'functionalReprints' => $card_filters_other]);

		if ($has_obsoletes) {
			$cards = $cards->where(function($q) use ($card_filters_related, $term) {

				if ($term !== "")
					$q->where('name', $term)
					->orWhereHas('superiors', $card_filters_related)
					->orWhereHas('inferiors', $card_filters_related);
				else
					$q->whereHas('superiors', $card_filters_related);
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
			if (count($card->functionality->similiarcards) > 0) {
				$cards[$i]->functionality->similiarcards = $card->functionality->similiarcards->reject(function($item) use ($card) {
					return ($item->functionality_id === $card->functionality_id);
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
					'superiors' => function($q) { $q->relatedGuiOnly(); },
					'functionality.similiarcards' => function($q) { $q->guiOnly(); }
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

				$card->functionality->similiarcards = $card->functionality->similiarcards->reject(function($item) use ($card) {
					return ($card->functionality_id === $item->functionality_id);
				})->values();
			}
		}

		return view('card.create')->with(['card' => $card, 'inferiors' => $inferiors, 'superiors' => $superiors]);
	}

	public function upgradeview(Card $card)
	{
		$card->load([
			'functionalReprints' => function($q) { $q->guiOnly(); }, 
			'superiors' => function($q) { $q->relatedGuiOnly(); },
			'functionality.similiarcards' => function($q) { $q->guiOnly(); }]);

		// Remove self from reprints
		$card->functionalReprints = $card->functionalReprints->reject(function($item) use ($card) {
			return ($card->id === $item->id);
		})->values();

		$card->functionality->similiarcards = $card->functionality->similiarcards->reject(function($item) use ($card) {
			return ($card->functionality_id === $item->functionality_id);
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

	public function voteHelpLowOnVotes(Request $request) {

		$ip = $request->ip();
		$low_votes_not_this_ip = function($q) use ($ip) { 
			$q->relatedGuiOnly()
				->whereRaw("(obsoletes.downvotes > 0 or obsoletes.upvotes > 0)") // 0 / 0 votes are added by the automation, no need to vote for those
				->whereRaw("((obsoletes.downvotes + obsoletes.upvotes) < 8 and ABS(CAST(obsoletes.downvotes AS SIGNED) - CAST(obsoletes.upvotes AS SIGNED)) < 5)")
				->whereNotExists(function($q) use ($ip) {
					$q->select(DB::raw(1))->from('votes')->whereColumn('obsoletes.id', 'votes.obsolete_id')->where('ip', $ip);
				});
		};

		$inferior = Card::with(['superiors' => $low_votes_not_this_ip])
			->guiOnly()
			->whereHas('superiors', $low_votes_not_this_ip)
			->inRandomOrder()
			->first();

		$superior = $inferior ? $inferior->superiors->random() : null;

		return view('votehelp')->with(['inferior' => $inferior, 'superior' => $superior]);
	}

	public function voteHelpDisputed(Request $request) {

		$ip = $request->ip();
		$disputed_not_this_ip = function($q) use ($ip) { 
			$q->relatedGuiOnly()
				->where("obsoletes.downvotes", '>', 0)->where("obsoletes.upvotes", '>', 0)
				->whereRaw("(ABS(CAST(obsoletes.downvotes AS SIGNED) - CAST(obsoletes.upvotes AS SIGNED)) < 2 or ((obsoletes.downvotes / obsoletes.upvotes) > 0.5 and (obsoletes.downvotes / obsoletes.upvotes) < 1.5))")
				->whereNotExists(function($q) use ($ip) {
					$q->select(DB::raw(1))->from('votes')->whereColumn('obsoletes.id', 'votes.obsolete_id')->where('ip', $ip);
				});
		};

		$inferior = Card::with(['superiors' => $disputed_not_this_ip])
			->guiOnly()
			->whereHas('superiors', $disputed_not_this_ip)
			->inRandomOrder()
			->first();

		$superior = $inferior ? $inferior->superiors->random() : null;

		return view('votehelp')->with(['inferior' => $inferior, 'superior' => $superior]);
	}

	public function voteHelpSpreadsheets(Request $request)
	{
		$retries_left = 10;

		$inferior = null;
		$superior = null;
		$suggestion = null;

		while ($retries_left) {

			$suggestion = Suggestion::inRandomOrder()->first();

			$inferior_name = $suggestion->inferiors[0] ?? '';
			$superior_name = $suggestion->superiors[0] ?? '';

			$inferior = Card::where('name', $inferior_name)->whereNull('main_card_id')->first();
			$superior = Card::where('name', $superior_name)->whereNull('main_card_id')->first();

			if ($inferior && $superior)
				break;

			$retries_left--;
		}

		if ($inferior && $superior) {
			$labels = create_labels($inferior, $superior);

			// Create run-time relations (not saved to DB)
			$superior->setRelation('pivot', $superior->newPivot($inferior, ['labels' => $labels, 'suggestion_id' => $suggestion->id], 'labelings', true));
			$inferior->setRelation('superiors', new \Illuminate\Database\Eloquent\Collection([$superior]));
		}

		return view('votehelp')->with(['inferior' => $inferior, 'superior' => $superior]);
	}

	public function addSuggestion(Request $request, $suggestion_id) {

		$suggestion = Suggestion::find($suggestion_id);
		if (!$suggestion)
			return response()->json(['status' => 'This suggestion no longer exists']);
			
		$inferior_name = $suggestion->inferiors[0] ?? '';
		$superior_name = $suggestion->superiors[0] ?? '';

		$inferior = Card::with(['functionality'])->where('name', $inferior_name)->whereNull('main_card_id')->first();
		$superior = Card::with(['functionality'])->where('name', $superior_name)->whereNull('main_card_id')->first();

		if (!$inferior || !$superior)  {
			$suggestion->deleteLatest();	// Delete suggestions to invalid cards
			return response()->json(['status' => 'Card not found']);
		}

		// Verify we are handling the same suggestion internally as user was in UI
		if ($superior->id != $request->input('superior_id'))
			return response()->json(['status' => 'This suggestion no longer exists']);

		// Suggestions are deleted once voted
		$suggestion->deleteLatest();

		if ($inferior->functionality->group_id == $superior->functionality->group_id)
			return response()->json(['status' => 'The cards belong to same functionality group']);

		if (!$superior->isEqualOrBetterThan($inferior))
			return response()->json(['status' => "The superior card doesn't seem to be better"]);

		DB::transaction(function () use ($inferior, $superior) {
			create_obsolete($inferior, $superior, true);

			// Add vote
			$obsolete = Obsolete::where('superior_functionality_group_id', $superior->functionality->group_id)
				->where('inferior_functionality_group_id', $inferior->functionality->group_id)
				->first();

			$this->addVote($obsolete->id, true);
		});
		
		return response()->json(['status' => 'ok']);
	}

	public function ignoreSuggestion(Request $request, $suggestion_id) {

		$suggestion = Suggestion::find($suggestion_id);
		if (!$suggestion)
			return response()->json(['status' => 'This suggestion no longer exists']);

		$superior_name = $suggestion->superiors[0] ?? '';
		$superior = Card::with(['functionality'])->where('name', $superior_name)->whereNull('main_card_id')->first();

		if (!$superior) {
			$suggestion->deleteLatest();	// Delete suggestions to invalid cards
			return response()->json(['status' => 'Card not found']);
		}

		// Verify we are handling the same suggestion internally as user was in UI
		if ($superior->id != $request->input('superior_id'))
			return response()->json(['status' => 'This suggestion no longer exists']);

		// Suggestions are deleted once voted
		$suggestion->deleteLatest();

		return response()->json(['status' => 'ok']);
		
	}

	public function testSuggestion(Request $request) {

		$request->validate([
			'inferior_id' => 'required',
			'superior_id' => 'required',
		]);

		$superior = Card::with(['functionality', 'superiors'])->where('id', $request->input('superior_id'))->whereNull('main_card_id')->first();
		$inferior = Card::with(['functionality', 'superiors', 'inferiors'])->where('id', $request->input('inferior_id'))->whereNull('main_card_id')->first();

		$status = [
			'reason' => null,
			'bootstrap_mode' => null
		];

		if (!$inferior || !$superior) {
			$status['reason'] = \Lang::get('card.validation.not-found');
			$status['bootstrap_mode'] = 'alert-danger';
			return response()->json($status);
		}
		
		// Is better ?
		$result = $superior->isEqualOrBetterThan($inferior, true);
		if ($result !== true) {
			$status['reason'] = \Lang::get($result, ['superior' => $superior->name, 'inferior' => $inferior->name]);
			$status['bootstrap_mode'] = 'alert-danger';
		}

		// Already added ?
		else if ($inferior->superiors->pluck('functionality_id')->contains($superior->functionality_id)) {
			$status['reason'] = \Lang::get('card.validation.already-exists', ['superior' => $superior->name, 'inferior' => $inferior->name]);
			$status['bootstrap_mode'] = 'alert-info';
		}

		// Already added as the opposite (inferiors inferior or superiors superior) ?
		else if ($inferior->inferiors->pluck('functionality_id')->contains($superior->functionality_id) ||
				$superior->superiors->pluck('functionality_id')->contains($inferior->functionality_id)) {
			$status['reason'] = \Lang::get('card.validation.opposite-exists', ['superior' => $superior->name, 'inferior' => $inferior->name]);
			$status['bootstrap_mode'] = 'alert-warning';
		}
		
		return response()->json($status);
	}
}
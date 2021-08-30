<?php

namespace App\Http\Controllers;

use App\Card;
use App\Obsolete;
use App\Labeling;
use App\FunctionalReprint;
use Illuminate\Http\Request;

use App\Helpers;

class ApiController extends Controller
{

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function obsoletes(Request $request, $search = null)
	{

		$select = ['name', 'multiverse_id', 'functional_reprints_id'];

		$labelings = Labeling::with([
			'superiors' => function($q) use ($select) { return $q->select(array_merge($select, ['functionality_id'])); }, 
			'inferiors' => function($q) use ($select) { return $q->select(array_merge($select, ['functionality_id'])); },
			'obsolete'
		])->whereNotNull('obsolete_id');

		if ($search !== null) {
			$term = escapeLike($search);
			$labelings = $labelings->whereHas('inferiors', function($q) use ($term) {
				$q->where('name', 'like', $term . '%');
			});
		}

		$labelings = $labelings->orderBy('created_at', 'desc')->paginate(50);

		$collection = collect([]);
		foreach ($labelings as $labeling) {
			foreach ($labeling->superiors as $superior) {
				foreach ($labeling->inferiors as $inferior) {
					$collection->push([
						'id' => $labeling->id,
						'upvotes' => $labeling->obsolete->upvotes,
						'downvotes' => $labeling->obsolete->downvotes,
						'created_at' => $labeling->created_at->toDateTimeString(),
						'updated_at' => $labeling->updated_at->toDateTimeString(),
						'inferior' => $inferior->only($select),
						'superior' => $superior->only($select),
						'labels' => $labeling->labels

					]);
				}
			}
		}

	    return response()->json($collection);
	}

	public function functional_reprints(Request $request)
	{

		$reprints = FunctionalReprint::with([
			'cards' => function($q) { $q->select(['name', 'multiverse_id', 'functional_reprints_id']); }
		])->paginate(200);

		$reprints->getCollection()->transform(function ($group) {

			$cards = $group->cards->map(function ($card) {
				return $card->only(['name', 'multiverse_id']);
			});

			return [
				'id' => $group->id,
				'typeline' => $group->typeline,
				'manacost' => $group->manacost, 
				'power' => $group->power, 
				'toughness' => $group->toughness, 
				'loyalty' => $group->loyalty, 
				'rules' => $group->rules,
				'cards' => $cards
			];

		});

		return response()->json($reprints);
	}

	public function guide()
	{
		return view('apiguide');
	}
}
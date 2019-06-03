<?php

namespace App\Http\Controllers;

use App\Card;
use App\Obsolete;
use App\Suggestion;
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
	public function obsoletes($search = null)
	{

		$obsoletes = Obsolete::with(['superior', 'inferior']);

		if ($search !== null) {
			$term = escapeLike($search);
			$obsoletes = $obsoletes->whereHas('inferior', function($q) use ($term) {
				$q->where('name', 'like', $term . '%');
			});
		}

		$obsoletes = $obsoletes->orderBy('created_at', 'desc')->paginate(50);

		$obsoletes->getCollection()->transform(function ($obsolete) {

			return [
				'id' => $obsolete->id,
				'upvotes' => $obsolete->upvotes,
				'downvotes' => $obsolete->downvotes,
				'created_at' => $obsolete->created_at->toDateTimeString(),
				'updated_at' => $obsolete->updated_at->toDateTimeString(),
				'inferior' => [
					'name' => $obsolete->inferior->name,
					'multiverseid' => $obsolete->inferior->multiverse_id,
					'functional_reprints_id' => $obsolete->superior->functional_reprints_id
				],
				'superior' => [
					'name' => $obsolete->superior->name,
					'multiverseid' => $obsolete->superior->multiverse_id,
					'functional_reprints_id' => $obsolete->superior->functional_reprints_id
				],
				'labels' => $obsolete->labels

			];
		});

	    return response()->json($obsoletes);
	}

	public function functional_reprints()
	{
		$reprints = FunctionalReprint::with(['cards'])->paginate(200);

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
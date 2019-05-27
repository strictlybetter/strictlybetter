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

	    return response()->json($obsoletes);
	}

	public function functional_reprints()
	{
		$reprints = FunctionalReprint::with(['cards'])->paginate(200);

		$formatted = [];
		foreach ($reprints as $group) {

			$formatted[] = [
				'id' => $group->id,
				'typeline' => $group->typeline,
				'manacost' => $group->manacost, 
				'power' => $group->power, 
				'toughness' => $group->toughness, 
				'loyalty' => $group->loyalty, 
				'rules' => $group->rules,
				'cards' => $group->cards->pluck('name')
			];
		}

		return response()->json($formatted);
	}

	public function guide()
	{
		return view('apiguide');
	}
}
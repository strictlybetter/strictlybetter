<?php

namespace App\Http\Controllers;

use App\Card;
use App\Obsolete;
use App\Suggestion;
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

	public function guide()
	{
		return view('apiguide');
	}
}
<?php

namespace App\Http\Middleware;

use Closure;

class ValidatePaginationBounds
{
	public function handle($request, Closure $next)
	{
        $request->validate([
            'page' => 'integer|min:1'
        ]);

        return $next($request);
	}
}

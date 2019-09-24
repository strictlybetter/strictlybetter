<?php

namespace App\Http\Middleware;

use Closure;

class Cors
{
	public function handle($request, Closure $next)
	{
		$headers = [
			'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods'=> 'GET, OPTIONS',
            'Access-Control-Allow-Headers'=> 'Accept, Accept-Charset, Accept-Language, Cache-Control, Content-Language, Content-Type, Host, If-Modified-Since, Keep-Alive, Origin, Referer, User-Agent, X-Requested-With',
            'Vary' => 'Origin, Access-Control-Request-Headers, Access-Control-Request-Method'
        ];

		if ($request->getMethod() == "options") {
            return Response::make('OK', 200, $headers);
        }

		$response = $next($request);
        foreach ($headers as $key => $value) {
        	$response->header($key, $value);
        }

        return $response;
	}
}

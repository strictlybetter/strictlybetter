<?php

use Illuminate\Http\Request;

use App\Http\Controllers\ApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});*/


Route::match(['get', 'options'], '/obsoletes/{search?}', [ApiController::class, 'obsoletes'])->name('api.obsoletes');
Route::match(['get', 'options'], '/functional_reprints/{id?}', [ApiController::class, 'functional_reprints'])->name('api.functional_reprints');

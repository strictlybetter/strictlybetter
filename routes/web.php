<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\DeckController;
use App\Http\Controllers\ApiController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::pattern('card', '[0-9]+');
Route::pattern('obsolete', '[0-9]+');
Route::pattern('suggestion', '[0-9]+');

// Prevent requests from accessing multiface card' faces directly (main_card_id must be null)
Route::bind('card', function ($id) {
	return App\Card::where('id', $id)->whereNull('main_card_id')->first() ?? abort(404);
});

// CSRF refresh route (still requires valid csrf token)
Route::post('/refresh-csrf', function() { return response()->json(['token' => csrf_token()]); });

Route::get('/', [CardController::class, 'index'])->name('index');

Route::get('/deck', [DeckController::class, 'index'])->name('deck.index');
Route::post('/upgrade_deck', [DeckController::class, 'upgrade'])->name('deck.upgrade');

Route::get('/quicksearch', [CardController::class, 'quicksearch'])->name('card.quicksearch');
Route::get('/card_autocomplete', [CardController::class, 'cardAutocomplete'])->name('card.autocomplete');
Route::post('/card/search', [CardController::class, 'search'])->name('card.search');
Route::get('/upgradeview/{card}', [CardController::class, 'upgradeview'])->name('card.upgradeview');
Route::get('/upgradeview/test-suggestion', [CardController::class, 'testSuggestion'])->name('card.test-suggestion');

Route::get('/card/{card?}', [CardController::class, 'create'])->name('card.create');
Route::post('/card', [CardController::class, 'store'])->name('card.store');

Route::post('/upvote/{obsolete}', [CardController::class, 'upvote'])->name('upvote');
Route::post('/downvote/{obsolete}', [CardController::class, 'downvote'])->name('downvote');

Route::get('/about', function () { return view('about'); })->name('about');
Route::get('/changelog', function () { return view('changelog'); })->name('changelog');

Route::prefix('votehelp')->group(function () {
	Route::get('low-on-votes', [CardController::class, 'voteHelpLowOnVotes'])->name('votehelp.low-on-votes');
	Route::get('spreadsheets', [CardController::class, 'voteHelpSpreadsheets'])->name('votehelp.spreadsheets');
	Route::get('disputed', [CardController::class, 'voteHelpDisputed'])->name('votehelp.disputed');

	Route::post('add-suggestion/{suggestion}', [CardController::class, 'addSuggestion'])->name('votehelp.add-suggestion');
	Route::post('ignore-suggestion/{suggestion}', [CardController::class, 'ignoreSuggestion'])->name('votehelp.ignore-suggestion');
});

// API
Route::get('/api-guide', [ApiController::class, 'guide'])->name('api.guide');


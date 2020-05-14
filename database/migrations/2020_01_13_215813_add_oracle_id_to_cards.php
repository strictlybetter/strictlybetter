<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOracleIdToCards extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::table('cards', function (Blueprint $table) {
			$table->string('oracle_id')->nullable();
		});

		// Load cards again to populate oracle_id
		Artisan::call('load-scryfall', ['callbacks' => ['query' => function($obj, $parent = null) {

			// Find existing card with same name, or scryfall_api attribute (unless this is a card face with a parent card)
			$q = App\Card::query();

			if ($parent) {
				$q = $q->where('main_card_id', $parent->id)->where('name', $obj->name);
			}
			else {
				$q = $q->whereNull('main_card_id')->where(function($q) use ($obj) {
					$q->where('name', $obj->name)->orWhere('scryfall_api', $obj->uri);
				});
			}
			return $q->orderBy('created_at', 'desc')->first();

        }]]);

		// Remove bad (duplicate) cards we may have created earlier using the old naming method
		Artisan::call('remove-old-spoilers', []);

		// Replace index
		Schema::table('cards', function (Blueprint $table) {
			$table->dropIndex('cards_scryfall_api_index');
			$table->index('oracle_id');
		});
	}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cards', function (Blueprint $table) {
        	$table->dropColumn('oracle_id');
            $table->index('scryfall_api');
        });
    }
}

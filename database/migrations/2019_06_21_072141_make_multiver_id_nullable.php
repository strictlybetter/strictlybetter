<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeMultiverIdNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedInteger('multiverse_id')->nullable()->change();

            $table->string('scryfall_img')->nullable();
            $table->string('scryfall_api')->nullable();
            $table->string('scryfall_link')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    	// Must first remove all cards with multiverse id NULL
    	\App\Card::whereNull('multiverse_id')->delete();

        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedInteger('multiverse_id')->nullable(false)->change();

            $table->dropColumn('scryfall_img');
            $table->dropColumn('scryfall_api');
            $table->dropColumn('scryfall_link');
        });
    }
}

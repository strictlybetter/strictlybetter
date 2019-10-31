<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeTableCardtypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cardtypes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('section');	// Sections: Supertype, Type, Subtype
            $table->string('type');		// Types: Creature, Spell, Artifact, Planeswalker, Land, Enchantment
            $table->string('key');		// Actual name

            $table->unique(['section', 'key']);
        });

        /*
        // Can't index JSON columns. Should look into other options.
        Schema::table('cards', function (Blueprint $table) {
            $table->index('legalities');
            $table->index('subtypes');
        });
        */
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    	/*
    	Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex('cards_legalities_index');
            $table->dropIndex('cards_subtypes_index');
        });
        */

        Schema::dropIfExists('cardtypes');
    }
}

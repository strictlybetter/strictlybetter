<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeFunctionalReprintsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('functional_reprints', function (Blueprint $table) {
            $table->increments('id');
            $table->text('rules');
            $table->string('typeline');
            $table->string('manacost')->nullable();
            $table->string('power', 20)->nullable();
            $table->string('toughness', 20)->nullable();
            $table->string('loyalty', 20)->nullable();
            $table->timestamps();

            $table->index(['typeline', 'manacost', 'power', 'toughness', 'loyalty'], 'descriptor_index');
        });

        Schema::table('cards', function (Blueprint $table) {

        	$table->unsignedInteger('functional_reprints_id')->nullable();

        	$table->foreign('functional_reprints_id')->references('id')->on('functional_reprints')->onDelete('set null');
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
    		$table->dropForeign(['functional_reprints_id']);
            $table->dropColumn('functional_reprints_id');
        });

        Schema::dropIfExists('functional_reprints');
    }
}

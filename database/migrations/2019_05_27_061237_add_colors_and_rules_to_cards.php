<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColorsAndRulesToCards extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
        	$table->json('colors')->nullable();
        	$table->json('color_identity')->nullable();
        	$table->text('rules')->default("");
        	$table->string('power', 20)->nullable();
        	$table->string('toughness', 20)->nullable();
        	$table->string('loyalty', 20)->nullable();
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
            $table->dropColumn('colors');
            $table->dropColumn('color_identity');
            $table->dropColumn('rules');
            $table->dropColumn('power');
            $table->dropColumn('toughness');
            $table->dropColumn('loyalty');
        });
    }
}

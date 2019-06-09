<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSubstitutedRulesToCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->text('substituted_rules')->default("");
            $table->json('manacost_sorted')->nullable();

            $table->index([DB::raw('substituted_rules(191)')]);
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

        	$table->dropColumn('manacost_sorted');
            $table->dropColumn('substituted_rules');
        });
    }
}

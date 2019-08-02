<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMultifaceCardSupport extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedInteger('main_card_id')->nullable();
            $table->boolean('flip')->default(false);

            $table->foreign('main_card_id')->references('id')->on('cards')->onDelete('cascade');
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

        	$table->dropForeign('cards_main_card_id_foreign');

        	$table->dropColumn('flip');
            $table->dropColumn('main_card_id');
        });
    }
}

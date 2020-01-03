<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeCardUniqueRule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropUnique('cards_name_unique');

            $table->unique(['name', 'main_card_id']);
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
            $table->dropUnique('cards_name_main_card_id_unique');

            $table->unique(['name']);
        });
    }
}

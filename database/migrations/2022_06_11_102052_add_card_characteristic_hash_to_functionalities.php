<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCardCharacteristicHashToFunctionalities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('functionalities', function (Blueprint $table) {
            $table->string('hash', 16)->default("");
        });

        Schema::table('functionality_groups', function (Blueprint $table) {
            $table->string('hash', 16)->default("");
        });

        App\Card::with('functionality.group')->whereNull('main_card_id')->chunkById(1000, function($cards) {
            foreach ($cards as $card) {

                $functionality = $card->functionality;
                $group = $functionality->group;

                $functionality->hash = hash('xxh3', $card->functionality_line);
                $functionality->save();

                $group->hash = hash('xxh3', $card->functionality_group_line);
                $group->save();

            }
        }, $column = 'id');
        
        Schema::table('functionalities', function (Blueprint $table) {
            $table->index('hash');
        });

        Schema::table('functionality_groups', function (Blueprint $table) {
            $table->index('hash');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('functionality_groups', function (Blueprint $table) {
            $table->dropColumn('hash');
        });

        Schema::table('functionalities', function (Blueprint $table) {
            $table->dropColumn('hash');
        });
    }
}

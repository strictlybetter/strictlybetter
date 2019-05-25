<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->unsignedInteger('multiverse_id');
            
            $table->json('legalities');
            $table->string('manacost');
            $table->integer('cmc')->nullable();
            $table->json('supertypes');
            $table->json('subtypes');
            $table->json('types');

            $table->unsignedDecimal('price', 8, 2)->default(0)->index();

            $table->timestamps();

            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cards');
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeObsoletesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('obsoletes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('inferior_card_id');
            $table->unsignedInteger('superior_card_id');
            $table->unsignedInteger('upvotes')->default(0);
            $table->unsignedInteger('downvotes')->default(0);
            $table->timestamps();

            $table->foreign('inferior_card_id')->references('id')->on('cards')->onDelete('cascade');
            $table->foreign('superior_card_id')->references('id')->on('cards')->onDelete('cascade');

            $table->unique(['inferior_card_id', 'superior_card_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('obsoletes');
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeExcerptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('excerpts', function (Blueprint $table) {
            $table->increments('id');
            $table->text('text');
            $table->tinyInteger('positive');
            $table->tinyInteger('regex');
            $table->integer('positivity_points')->default(0);
            $table->integer('negativity_points')->default(0);

            $table->index([DB::raw('text(191)')]);
            $table->index('positive');
            $table->index('regex');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('excerpts');
    }
}

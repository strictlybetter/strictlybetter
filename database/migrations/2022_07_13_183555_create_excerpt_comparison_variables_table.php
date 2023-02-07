<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExcerptComparisonVariablesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('excerpt_comparison_variables', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedInteger('comparison_id');
            $table->unsignedInteger('inferior_variable_id');
            $table->unsignedInteger('superior_variable_id');

            $table->tinyInteger('more_is_better')->nullable();

            $table->integer('points_for_more')->default(0);
            $table->integer('points_for_less')->default(0);

            $table->foreign('comparison_id')->references('id')->on('excerpt_comparisons')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('inferior_variable_id')->references('id')->on('excerpt_variables')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('superior_variable_id')->references('id')->on('excerpt_variables')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('excerpt_comparison_variables');
    }
}

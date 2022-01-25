<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeExcerptsVariablesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('excerpts', function (Blueprint $table) {
            $table->dropColumn('regex');
        });

         // cards may have multiple copies of the same excerpt, so add amount
        Schema::table('excerpt_group', function (Blueprint $table) {
            $table->integer('amount')->default(1);
        });

        Schema::create('excerpt_comparisons', function (Blueprint $table) {

            $table->increments('id');
            $table->unsignedInteger('superior_excerpt_id');
            $table->unsignedInteger('inferior_excerpt_id');
            $table->integer('reliability_points')->default(1);

            $table->foreign('superior_excerpt_id')->references('id')->on('excerpts')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('inferior_excerpt_id')->references('id')->on('excerpts')->onUpdate('cascade')->onDelete('cascade');

            $table->unique(['superior_excerpt_id', 'inferior_excerpt_id'], 'excerpt_betterness_unique');
        });

        Schema::create('excerpt_variables', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('excerpt_id');
            $table->unsignedInteger('capture_id');
            $table->string('capture_type');
            $table->tinyInteger('more_is_better')->nullable();

            $table->integer('points_for_more')->default(0);
            $table->integer('points_for_less')->default(0);

            $table->foreign('excerpt_id')->references('id')->on('excerpts')->onUpdate('cascade')->onDelete('cascade');
        });
        
        Schema::create('excerpt_variable_values', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('group_id');
            $table->unsignedInteger('variable_id');
            $table->json('value');

            $table->foreign('group_id')->references('id')->on('functionality_groups')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('variable_id')->references('id')->on('excerpt_variables')->onUpdate('cascade')->onDelete('cascade');
        });
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('excerpt_variable_values');
        Schema::dropIfExists('excerpt_variables');
        Schema::dropIfExists('excerpt_comparisons');

        Schema::table('excerpt_group', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        Schema::table('excerpts', function (Blueprint $table) {
            $table->tinyInteger('regex');
        });
    }
}

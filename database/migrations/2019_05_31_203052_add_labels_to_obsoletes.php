<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLabelsToObsoletes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('obsoletes', function (Blueprint $table) {
        	$table->json('labels');
        	/*
            $table->boolean('more_colors')->default(false)->index();
            $table->boolean('more_colored_mana')->default(false)->index();
            $table->boolean('subtypes_differ')->default(false)->index();
            $table->boolean('types_differ')->default(false)->index();
            $table->boolean('supertypes_differ')->default(false)->index();
            $table->boolean('less_colors')->default(false)->index();
            $table->boolean('strictly_better')->default(false)->index();
            */
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('obsoletes', function (Blueprint $table) {
        	$table->dropColumn('labels');
        	/*
            $table->dropColumn('more_colors');
            $table->dropColumn('more_colored_mana');
            $table->dropColumn('subtypes_differ');
            $table->dropColumn('types_differ');
            $table->dropColumn('supertypes_differ');
            $table->dropColumn('less_colors');
            $table->dropColumn('strictly_better');
            */
        });
    }
}

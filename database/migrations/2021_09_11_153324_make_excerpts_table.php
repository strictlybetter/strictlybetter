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
            $table->tinyInteger('positive')->nullable();
            $table->tinyInteger('regex');
            $table->text('text');
            $table->integer('positivity_points')->default(0);
            $table->integer('negativity_points')->default(0);

            $table->index('positive');
            $table->index([DB::raw('text(191)')]);
        });

        Schema::create('excerpt_group', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('excerpt_id');
            $table->unsignedInteger('group_id');

            $table->foreign('group_id')->references('id')->on('functionality_groups')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('excerpt_id')->references('id')->on('excerpts')->onUpdate('cascade')->onDelete('cascade');

            $table->unique(['group_id', 'excerpt_id']);
        });

        // Change column orders for performance
        DB::statement('
            ALTER TABLE `strictlybetter`.`cards` 
            CHANGE COLUMN `main_card_id` `main_card_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `id`,
            CHANGE COLUMN `functionality_id` `functionality_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `main_card_id`,
            CHANGE COLUMN `multiverse_id` `multiverse_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `functionality_id`,
            CHANGE COLUMN `functional_reprints_id` `functional_reprints_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `multiverse_id`,
            CHANGE COLUMN `oracle_id` `oracle_id` VARCHAR(191) NULL DEFAULT NULL AFTER `functional_reprints_id`,
            CHANGE COLUMN `created_at` `created_at` TIMESTAMP NULL DEFAULT NULL AFTER `name`,
            CHANGE COLUMN `updated_at` `updated_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`,
            CHANGE COLUMN `hybridless_cmc` `hybridless_cmc` DECIMAL(8,1) NULL DEFAULT NULL AFTER `cmc`,
            CHANGE COLUMN `flip` `flip` TINYINT(1) NOT NULL DEFAULT \'0\' AFTER `loyalty`,
            CHANGE COLUMN `typeline` `typeline` VARCHAR(191) NOT NULL AFTER `manacost_sorted`,
            CHANGE COLUMN `price` `price` DECIMAL(8,2) UNSIGNED NOT NULL DEFAULT \'0.00\' AFTER `typeline`,
            CHANGE COLUMN `rules` `rules` TEXT NOT NULL AFTER `price`;
        ');

        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex(['price']);
            $table->index(['cmc', 'hybridless_cmc', 'power', 'toughness', 'loyalty']); 
        });
        /*
        Schema::create('excerpt_variables', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('excerpt_id');
            $table->unsignedInteger('capture_id');
            $table->string('capture_type');
            $table->tinyInteger('more_is_better')->nullable();

            //$table->integer('points_for_more')->default(0);
            //$table->integer('points_for_less')->default(0);

            $table->foreign('excerpt_id')->references('id')->on('excerpts')->onUpdate('cascade')->onDelete('cascade');
            //$table->index('more_is_better');
        });

        Schema::create('excerpt_variable_values', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('excerpt_group_id');
            $table->unsignedInteger('variable_id');
            $table->string('value');
            $table->integer('integer_value')->index();

            $table->foreign('excerpt_group_id')->references('id')->on('excerpt_group')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('variable_id')->references('id')->on('excerpt_variables')->onUpdate('cascade')->onDelete('cascade');
        });
        */
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //Schema::dropIfExists('excerpt_variable_values');
        //Schema::dropIfExists('excerpt_variables');
        Schema::dropIfExists('excerpt_group');
        Schema::dropIfExists('excerpts');

        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex('cards_cmc_hybridless_cmc_power_toughness_loyalty_index');
            $table->index('price');
        });
    }
}

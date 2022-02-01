<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexablePowerToughnessToCards extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex(['cmc', 'hybridless_cmc', 'power', 'toughness', 'loyalty']);

            $table->decimal('power_numeric', 3, 1)->nullable()->storedAs('(case when (`power` regexp "^(\\\+|\\\-)?[0-9]*\\\\.?[0-9]*$") then `power` else NULL end)');
            $table->decimal('toughness_numeric', 3, 1)->nullable()->storedAs('(case when (`toughness` regexp "^(\\\+|\\\-)?[0-9]*\\\\.?[0-9]*$") then `toughness` else NULL end)');
            $table->decimal('loyalty_numeric', 3, 1)->nullable()->storedAs('(case when (`loyalty` regexp "^(\\\+|\\\-)?[0-9]*\\\\.?[0-9]*$") then `loyalty` else NULL end)');

            $table->index(['cmc', 'hybridless_cmc', 'power_numeric', 'toughness_numeric', 'loyalty_numeric'], 'cards_search_obsolete_index');
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

            $table->dropIndex('cards_search_obsolete_index');

            $table->dropColumn('loyalty_numeric');
            $table->dropColumn('toughness_numeric');
            $table->dropColumn('power_numeric');

            $table->index(['cmc', 'hybridless_cmc', 'power', 'toughness', 'loyalty']);
            //
        });
    }
}

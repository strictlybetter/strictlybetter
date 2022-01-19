<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHybridlessCmcToCards extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->decimal('hybridless_cmc', 8, 1)->nullable();
            $table->decimal('cmc', 8, 1)->nullable()->change();
        });

        $cards = App\Card::all();
        foreach ($cards as $card) {
            $card->timestamps = false;

            $manacost = App\Manacost::createFromManacostString($card->manacost, $card->cmc);
            $card->hybridless_cmc = $manacost->hybridless_cmc;
            $card->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('hybridless_cmc');
            $table->integer('cmc')->nullable()->change();
        });
    }
}

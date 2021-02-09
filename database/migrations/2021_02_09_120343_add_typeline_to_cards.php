<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTypelineToCards extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('typeline')->default("");
        });

        if (App\Card::count() > 0) {

            // Load typeline
            Artisan::call('load-scryfall', []);
            DB::statement('ALTER TABLE cards ALTER COLUMN typeline DROP DEFAULT');

            // Reprints use wrong hyphen, upadte it
            $reprints = App\FunctionalReprint::with(['cards'])->get();
            foreach ($reprints as $reprint) {
                $reprint->typeline = $reprint->cards->first()->typeline;
                $reprint->save();
            }
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
            $table->dropCOlumn('typeline');
        });

        if (App\Card::count() > 0) {

            // Revert to wrong hyphen
            $reprints = App\FunctionalReprint::with(['cards'])->get();
            foreach ($reprints as $reprint) {

                $reprint->typeline = $this->oldTypeLine($reprint->cards->first());
                $reprint->save();
            }
        }
    }

    public function oldTypeLine($card) {
        return trim(implode(" ", $card->supertypes) . " " . implode(" ", $card->types) . " - " . implode(" ", $card->subtypes), " -");
    }
}

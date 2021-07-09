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
            {
                $cards = App\Card::select(['id', 'main_card_id', 'supertypes', 'types', 'subtypes', 'typeline'])->get();
                foreach ($cards as $card) {
                    $typeline = "";
                    if (!empty($card->supertypes)) {
                        $typeline = $typeline . implode(" ", $card->supertypes) . (empty($card->types) ? "" : " ");
                    }

                    if (!empty($card->types))
                        $typeline = $typeline . implode(" ", $card->types);

                    if (!empty($card->subtypes))
                        $typeline = $typeline . ' — ' . implode(" ", $card->subtypes);

                    $card->timestamps = false;
                    $card->typeline = $typeline;
                    $card->save();
                }
            }

            // Reprints use wrong hyphen, upadte it
            $reprints = App\FunctionalReprint::with(['cards'])->get();
            foreach ($reprints as $reprint) {
                $sample = $reprint->cards->first();
                if ($sample) {
                    $reprint->typeline = $sample->typeline;
                    $reprint->save();
                }
                else
                    $reprint->delete();
            }
        }
        DB::statement('ALTER TABLE cards ALTER COLUMN typeline DROP DEFAULT');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('typeline');
        });

        if (App\Card::count() > 0) {

            // Revert to wrong hyphen
            $reprints = App\FunctionalReprint::with(['cards'])->get();
            foreach ($reprints as $reprint) {

                $sample = $reprint->cards->first();
                if ($sample) {
                    $reprint->typeline = $this->oldTypeLine($sample);
                    $reprint->save();
                }
                else
                    $reprint->delete();
            }
        }
    }

    public function oldTypeLine($card) {
        return trim(implode(" ", $card->supertypes) . " " . implode(" ", $card->types) . " - " . implode(" ", $card->subtypes), " -");
    }
}

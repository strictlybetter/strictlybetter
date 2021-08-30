<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/* 
    Classes as they were before this migration. 
    This functionality will not exist after. 
*/

use Illuminate\Database\Eloquent\Model;

class OldObsolete extends App\Obsolete {

    protected $table = "obsoletes";
    protected $casts = ['labels' => 'array'];

    public function superior()
    {
        return $this->belongsTo(App\Card::class, 'superior_card_id');
    }

    public function inferior()
    {
        return $this->belongsTo(App\Card::class, 'inferior_card_id');
    }
}

class MakeFunctionalitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('functionality_groups', function (Blueprint $table) {
            $table->increments('id');
        });
        Schema::create('functionalities', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('group_id');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedInteger('functionality_id')->nullable();
        });

        $this->update_card_functionality_ids();

        Schema::table('cards', function (Blueprint $table) {
            $table->foreign('functionality_id')->references('id')->on('functionalities')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::table('functionalities', function (Blueprint $table) {
            $table->foreign('group_id')->references('id')->on('functionality_groups')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::table('obsoletes', function (Blueprint $table) {
            $table->unsignedInteger('superior_functionality_group_id')->nullable();
            $table->unsignedInteger('inferior_functionality_group_id')->nullable();

            $table->unique(['superior_functionality_group_id', 'inferior_functionality_group_id'], 'unique_obsoletes_functionality_group_ids');

            $table->foreign('superior_functionality_group_id')->references('group_id')->on('functionalities')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('inferior_functionality_group_id')->references('group_id')->on('functionalities')->onUpdate('cascade')->onDelete('cascade');
        });

        /* New tables */
        
        Schema::create('labelings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('inferior_functionality_id');
            $table->unsignedInteger('superior_functionality_id');
            $table->unsignedInteger('obsolete_id')->nullable();

            $table->json('labels');
            $table->timestamps();

            $table->unique(['inferior_functionality_id', 'superior_functionality_id'], 'unique_labelings_functionality_ids');

            $table->foreign('inferior_functionality_id')->references('id')->on('functionalities')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('superior_functionality_id')->references('id')->on('functionalities')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('obsolete_id')->references('id')->on('obsoletes')->onUpdate('cascade')->onDelete('cascade');
        });
        

        try {       
            $this->update_obsolete_functionality_ids();
        } catch (Throwable $e) {

            Schema::dropIfExists('labelings');

            // Reset tables...
            Schema::table('obsoletes', function (Blueprint $table) {
                $table->dropForeign(['inferior_functionality_group_id']);
                $table->dropForeign(['superior_functionality_group_id']);
                $table->dropUnique('unique_obsoletes_functionality_group_ids');
                $table->dropColumn('inferior_functionality_group_id');
                $table->dropColumn('superior_functionality_group_id');
            });

            Schema::table('cards', function (Blueprint $table) {
                $table->dropForeign(['functionality_id']);
                $table->dropColumn('functionality_id');
            });

            Schema::dropIfExists('functionalities'); 
            Schema::dropIfExists('functionality_groups'); 

            Schema::enableForeignKeyConstraints();

            throw $e;
        }

        // Remove nullability from obsoletes table and drop card id columns
        Schema::table('obsoletes', function (Blueprint $table) {

            $table->unsignedInteger('superior_functionality_group_id')->nullable(false)->change();
            $table->unsignedInteger('inferior_functionality_group_id')->nullable(false)->change();

            $table->dropForeign(['superior_card_id']);
            $table->dropForeign(['inferior_card_id']);
            $table->dropUnique('obsoletes_inferior_card_id_superior_card_id_unique');

            $table->dropColumn('superior_card_id');
            $table->dropColumn('inferior_card_id');
            $table->dropColumn('labels');
        });
        Schema::enableForeignKeyConstraints();

    }

    public function update_card_functionality_ids(/*$attributes, $json_attributes*/) 
    {
        // Create functionalities from existing card data
        $card_groups = App\Card::whereNull('main_card_id')->orderBy('id')->get()->groupBy(['functionality_group_line', 'functionality_line'])->values();

        foreach ($card_groups as $groups) {

            $group = App\FunctionalityGroup::create([]);
            foreach ($groups as $cards) {

                $functionality = App\Functionality::create(['group_id' => $group->id]);
                foreach ($cards as $card) {
                    $card->functionality_id = $functionality->id;
                    $card->timestamps = false;
                    $card->save();
                }
            }
        }
    }

    public function update_obsolete_functionality_ids()
    {
        $obsoletes = OldObsolete::select('obsoletes.*')->with([
            'superior' => function($q) { $q->select('functionality_id', 'cards.id', 'main_card_id', 'group_id')->join('functionalities', 'functionality_id', '=', 'functionalities.id'); }, 
            'inferior' => function($q) { $q->select('functionality_id', 'cards.id', 'main_card_id', 'group_id')->join('functionalities', 'functionality_id', '=', 'functionalities.id'); }, 
        ])->get();
        foreach ($obsoletes as $obsolete) {

            // Migrate functionality specific labels to another table
            $superior_functionality_id = $obsolete->superior->functionality_id;
            $inferior_functionality_id = $obsolete->inferior->functionality_id;
            $existing_labels = App\Labeling::where('superior_functionality_id', $superior_functionality_id)->where('inferior_functionality_id', $inferior_functionality_id)->first();

            $superior_id = $obsolete->superior->group_id;
            $inferior_id = $obsolete->inferior->group_id;
            $existing_obsolete = OldObsolete::with(['votes'])->where('superior_functionality_group_id', $superior_id)->where('inferior_functionality_group_id', $inferior_id)->first();
            if ($existing_obsolete) {

                // Migrate votes, but only allow one vote for IP. 
                // The rest are dropped when obsolete is deleted at the end.
                $votes_to_migrate = $obsolete->votes()->whereNotIn('ip', $existing_obsolete->votes->pluck('ip'))->get();
                foreach ($votes_to_migrate as $vote) {
                    $vote->obsolete_id = $existing_obsolete->id;
                    if ($vote->upvote)
                        $existing_obsolete->upvotes++;
                    else
                        $existing_obsolete->downvotes++;
                    $vote->save();
                }
                $existing_obsolete->timestamps = false;
                $existing_obsolete->save();
                $obsolete->delete();

                $obsolete = $existing_obsolete;
            }
            else {
                $obsolete->superior_functionality_group_id = $superior_id;
                $obsolete->inferior_functionality_group_id = $inferior_id;
                $obsolete->timestamps = false;
                $obsolete->save();
            }

            if (!$existing_labels) {
                App\Labeling::create([
                    'superior_functionality_id' => $superior_functionality_id,
                    'inferior_functionality_id' => $inferior_functionality_id,
                    'labels' => $obsolete->labels,
                    'obsolete_id' => $obsolete->id,
                    'created_at' => $obsolete->created_at,
                    'updated_at' => $obsolete->updated_at
                ]);
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
        Schema::table('obsoletes', function (Blueprint $table) {
            $table->unsignedInteger('inferior_card_id')->nullable();
            $table->unsignedInteger('superior_card_id')->nullable();
        });

        $obsoletes = App\Obsolete::with(['superiors', 'inferiors'])->get();

        foreach ($obsoletes as $obsolete) {
            $obsolete->timestamps = false;
            $obsolete->inferior_card_id = $obsolete->inferiors->first()->id;
            $obsolete->superior_card_id = $obsolete->superiors->first()->id;
            $obsolete->save();
        }
        
        Schema::table('obsoletes', function (Blueprint $table) {
            $table->unsignedInteger('inferior_card_id')->nullable(false)->change();
            $table->unsignedInteger('superior_card_id')->nullable(false)->change();
            $table->json('labels');

            $table->unique(['inferior_card_id', 'superior_card_id']);

            $table->foreign('inferior_card_id')->references('id')->on('cards')->onDelete('cascade');
            $table->foreign('superior_card_id')->references('id')->on('cards')->onDelete('cascade');

            $table->dropForeign(['inferior_functionality_group_id']);
            $table->dropForeign(['superior_functionality_group_id']);
            $table->dropUnique('unique_obsoletes_functionality_group_ids');
            $table->dropColumn('inferior_functionality_group_id');
            $table->dropColumn('superior_functionality_group_id');     
        });

        Schema::dropIfExists('labelings');

        Schema::table('cards', function (Blueprint $table) {
            $table->dropForeign(['functionality_id']);
            $table->dropColumn('functionality_id');
        });

        Schema::dropIfExists('functionalities'); 
        Schema::dropIfExists('functionality_groups'); 
    }
}

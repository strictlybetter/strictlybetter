<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameSuggestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('community_suggestions', function (Blueprint $table) {
            $table->dropForeign(['obsolete_id']);
            $table->dropUnique(['obsolete_id', 'ip']);
        });

        Schema::rename('community_suggestions', 'votes');

        Schema::table('votes', function (Blueprint $table) {
            $table->foreign('obsolete_id')->references('id')->on('obsoletes')->onDelete('cascade');
            $table->unique(['obsolete_id', 'ip']);
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropForeign(['obsolete_id']);
            $table->dropUnique(['obsolete_id', 'ip']);
        });

        Schema::rename('votes', 'community_suggestions');

        Schema::table('community_suggestions', function (Blueprint $table) {
            $table->foreign('obsolete_id')->references('id')->on('obsoletes')->onDelete('cascade');
            $table->unique(['obsolete_id', 'ip']);
        });
    }
}

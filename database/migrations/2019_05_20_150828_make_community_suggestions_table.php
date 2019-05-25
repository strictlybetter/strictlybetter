<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeCommunitySuggestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('community_suggestions', function (Blueprint $table) {
        	$table->increments('id');
            $table->unsignedInteger('obsolete_id');
            $table->string('ip');
            $table->boolean('upvote');

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
        Schema::dropIfExists('community_suggestions');
    }
}

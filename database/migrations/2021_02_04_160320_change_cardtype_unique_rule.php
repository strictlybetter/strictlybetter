<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeCardtypeUniqueRule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cardtypes', function (Blueprint $table) {
            $table->dropUnique('cardtypes_section_key_unique');

            $table->unique(['section', 'key', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cardtypes', function (Blueprint $table) {
            $table->dropUnique('cardtypes_section_key_type_unique');

            $table->unique(['section', 'key']);
        });
    }
}

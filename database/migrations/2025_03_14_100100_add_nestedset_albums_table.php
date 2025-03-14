<?php

use App\Models\Album;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->unsignedInteger('_lft');
            $table->unsignedInteger('_rgt');
        });

        Album::fixTree();
    }

    public function down()
    {
        Schema::table('albums', function($table) {
            $table->dropColumn('_lft');
            $table->dropColumn('_rgt');
        });
    }
};

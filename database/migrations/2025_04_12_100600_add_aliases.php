<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up()
    {
        try {
            Schema::table('albums', function (Blueprint $table) {
                $table->string('alias', 64)->unique()->nullable();

                $table->unique(['alias', 'hash']);
            });

            Schema::create('legacy_aliases', function (Blueprint $table) {
                $table->id();
                $table->string('name', 64)->unique();
                $table->foreignId('album_id')->cascadeOnUpdate()->cascadeOnDelete();
                $table->timestamps();
            });
        }
        catch (\Exception $e) {
            $this->down();
            throw $e;
        }
    }

    public function down()
    {
        try {
            Schema::table('albums', function (Blueprint $table) {
                $table->dropUnique('albums_alias_hash_unique');
            });
        }
        catch (\Exception $e) {}

        dropColumnIfExists('albums', 'alias');

        Schema::dropIfExists('legacy_aliases');
    }
};

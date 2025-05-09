<?php

use App\Models\Album;
use App\Models\Image;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        try {
            Schema::table('albums', function (Blueprint $table) {
                $table->string('natural_sort_key', 255)->nullable();
            });

            DB::table('albums')->get()->each(function ($album) {
                $normalizedName = Album::normalizeName($album->name);
                DB::table('albums')
                    ->where('id', $album->id)
                    ->update(['natural_sort_key' => $normalizedName]);
            });

            Schema::table('albums', function (Blueprint $table) {
                $table->string('natural_sort_key', 255)->nullable(false)->change();
            });
        }
        catch (\Exception $e) {
            $this->down();
            throw $e;
        }
    }

    public function down()
    {
        dropColumnIfExists('albums', 'natural_sort_key');
    }
};

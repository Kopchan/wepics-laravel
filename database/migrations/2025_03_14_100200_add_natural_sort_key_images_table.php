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
        Schema::table('images', function (Blueprint $table) {
            $table->string('natural_sort_key', 255)->nullable();
        });

        try {
            DB::table('images')->get()->each(function ($image) {
                $normalizedName = Image::normalizeName($image->name);
                DB::table('images')
                    ->where('id', $image->id)
                    ->update(['natural_sort_key' => $normalizedName]);
            });

            Schema::table('images', function (Blueprint $table) {
                $table->string('natural_sort_key', 255)->nullable(false)->change();
            });
        }
        catch (\Exception $e) {
            Schema::table('images', function($table) {
                $table->dropColumn('natural_sort_key');
            });
            throw $e;
        }
    }

    public function down()
    {
        Schema::table('images', function($table) {
            $table->dropColumn('natural_sort_key');
        });
    }
};

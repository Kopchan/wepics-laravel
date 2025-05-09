<?php

use App\Enums\AgeRatingPreset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        try {
            Schema::create('age_ratings', function (Blueprint $table) {
                $table->id();
                $table->string ('code'       , 8  )->unique();
                $table->string ('name'       , 32 )->unique();
                $table->string ('description', 255)->nullable();
                $table->string ('color'      , 25 )->nullable();
                $table->integer('level')->default(0);
                $table->enum   ('preset', AgeRatingPreset::values())->default('show');
                $table->timestamps();
            });
            Schema::table('albums', function (Blueprint $table) {
                $table->foreignId('age_rating_id')
                    ->nullable()
                    ->references('id')
                    ->on('age_ratings')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
            Schema::table('images', function (Blueprint $table) {
                $table->foreignId('age_rating_id')
                    ->nullable()
                    ->references('id')
                    ->on('age_ratings')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }
        catch (\Exception $e) {
            $this->down();
            throw $e;
        }
    }

    public function down()
    {
        Schema::dropIfExists('age_ratings');
        dropColumnIfExists('albums', 'age_rating_id');
        dropColumnIfExists('images', 'age_rating_id');
    }
};

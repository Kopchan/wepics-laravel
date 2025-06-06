<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->string   ('name', 255);
            $table->string   ('hash', 32);
            $table->dateTime ('date');
            $table->unsignedBigInteger('size');
            $table->integer  ('width');
            $table->integer  ('height');
            $table->foreignId('album_id')
                ->nullable()
                ->references('id')
                ->on('albums')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['album_id', 'hash']);
            $table->unique(['album_id', 'name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('images');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('image_duplica', function (Blueprint $table) {
            $table->id();
            $table->string   ('name', 255);
            $table->foreignId('image_id')
                ->nullable()
                ->references('id')
                ->on('images')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['name', 'image_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('image_duplica');
    }
};

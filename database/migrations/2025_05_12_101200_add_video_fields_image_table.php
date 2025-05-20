<?php

use App\Models\Album;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('images', function (Blueprint $table) {
            $table->enum('type', ['image', 'video', 'audio', 'imageAnimated'])->default('image');
            $table->string            ('codec_name'        )->nullable();
            $table->unsignedBigInteger('frame_count'       )->nullable();
            $table->unsignedBigInteger('duration_ms'       )->nullable();
            $table->unsignedBigInteger('avg_frame_rate_num')->nullable();
            $table->unsignedBigInteger('avg_frame_rate_den')->nullable();
        });
    }

    public function down()
    {
        dropColumnIfExists('images', 'type');
        dropColumnIfExists('images', 'codec_name');
        dropColumnIfExists('images', 'frame_count');
        dropColumnIfExists('images', 'duration_ms');
        dropColumnIfExists('images', 'avg_frame_rate_num');
        dropColumnIfExists('images', 'avg_frame_rate_den');
    }
};

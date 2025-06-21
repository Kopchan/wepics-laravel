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
                $table->foreignId('owner_user_id')
                    ->nullable()
                    ->references('id')
                    ->on('users')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('quota')->default(0);
            });
        }
        catch (\Exception $e) {
            $this->down();
            throw $e;
        }
    }

    public function down()
    {
        dropColumnIfExists('albums', 'owner_user_id');
        dropColumnIfExists('users', 'quota');
    }
};

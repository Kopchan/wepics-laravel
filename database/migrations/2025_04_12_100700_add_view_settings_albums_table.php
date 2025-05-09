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
                $table->json('view_settings')->nullable();
            });
            Schema::table('users', function (Blueprint $table) {
                $table->json('view_settings_presets')->nullable();
            });
        }
        catch (\Exception $e) {
            $this->down();
            throw $e;
        }
    }

    public function down()
    {
        dropColumnIfExists('albums', 'view_settings');
        dropColumnIfExists('users', 'view_settings_presets');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        try {
            // Добавляем поле guest_allow в таблицу albums
            Schema::table('albums', function (Blueprint $table) {
                $table->boolean('guest_allow')->nullable();
            });

            // Переносим правила для гостей из access_rights в albums
            DB::table('access_rights')
                ->whereNull('user_id')
                ->get()
                ->each(function ($accessRight) {
                    DB::table('albums')
                        ->where('id', $accessRight->album_id)
                        ->update(['guest_allow' => $accessRight->allowed]);
                });

            // Удаляем записи для гостей из access_rights
            DB::table('access_rights')->whereNull('user_id')->delete();

            // Убираем nullable с поля user_id в таблице access_rights
            Schema::table('access_rights', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable(false) // Убираем nullable
                    ->change();
            });
        } catch (\Exception $e) {
            $this->down();
            throw $e;
        }
    }

    public function down()
    {
        dropColumnIfExists('albums', 'guest_allow');
    }
};

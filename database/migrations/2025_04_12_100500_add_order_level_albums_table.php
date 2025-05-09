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
                $table->integer('order_level')->default(0);
            });
        }
        catch (\Exception $e) {
            $this->down();
            throw $e;
        }
    }

    public function down()
    {
        dropColumnIfExists('albums', 'order_level');
    }
};

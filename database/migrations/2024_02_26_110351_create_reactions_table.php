<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReactionsTable extends Migration
{
    public function up()
    {
        Schema::create('reactions', function (Blueprint $table) {
           $table->id();
           $table->string("value",4);
           $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('reactions');
    }
}

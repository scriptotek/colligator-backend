<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOntosaursTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ontosaurs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('url')->unique();
            $table->mediumText('nodes');
            $table->mediumText('links');
            $table->string('topnode');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('ontosaurs');
    }
}
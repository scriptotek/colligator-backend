<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCoversTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('covers', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('document_id')->unsigned();
            $table->string('url');
            $table->string('mime')->nullable();
            $table->integer('width')->nullable()->unsigned();
            $table->integer('height')->nullable()->unsigned();
            $table->timestamps();
            $table->index('url');

            $table->foreign('document_id')
                ->references('id')->on('documents')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('covers');
    }
}

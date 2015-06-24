<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSubjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
        });

        Schema::create('document_subject', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('document_id')->unsigned();
            $table->integer('subject_id')->unsigned();

            $table->foreign('document_id')
                ->references('id')->on('documents')
                ->onDelete('cascade');

            $table->foreign('subject_id')
                ->references('id')->on('subjects')
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
        Schema::drop('document_subject');
        Schema::drop('subjects');
    }

}

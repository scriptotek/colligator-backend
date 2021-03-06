<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('bibsys_id')->unique();
            $table->mediumText('bibliographic');
            $table->mediumText('holdings');
        });

        Schema::create('collection_document', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('collection_id')->unsigned();
            $table->integer('document_id')->unsigned();
            $table->unique(['collection_id', 'document_id']);

            $table->foreign('collection_id')
                ->references('id')->on('collections')
                ->onDelete('cascade');

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
        Schema::drop('collection_document');
        Schema::drop('documents');
    }
}

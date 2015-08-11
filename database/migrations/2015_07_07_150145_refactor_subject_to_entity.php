<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class RefactorSubjectToEntity extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('document_subject', function (Blueprint $table) {
            $table->dropForeign('document_subject_subject_id_foreign');
        });

        Schema::rename('document_subject', 'entities');

        Schema::table('entities', function (Blueprint $table) {
            $table->renameColumn('subject_id', 'entity_id');
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->string('entity_type', 20)->nullable()->index();
        });

        DB::update('UPDATE entities SET entity_type=?', ['Colligator\\Subject']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn('entity_type');
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->renameColumn('entity_id', 'subject_id');
        });

        Schema::rename('entities', 'document_subject');

        Schema::table('document_subject', function (Blueprint $table) {
            $table->foreign('subject_id')
                ->references('id')->on('subjects')
                ->onDelete('cascade');
        });

    }
}

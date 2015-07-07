<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class RefactorSubjectToAuthority extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::rename('document_subject', 'authorities');

        Schema::table('authorities', function (Blueprint $table) {
            $table->renameColumn('subject_id', 'authority_id');
        });

        Schema::table('authorities', function (Blueprint $table) {
            $table->string('authority_type', 20)->nullable()->index();
        });

        DB::update('UPDATE authorities SET authority_type=?', ['Colligator\\Subject']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('authorities', function (Blueprint $table) {
           $table->dropIndex('authorities_authority_type_index');
        });

        Schema::table('authorities', function (Blueprint $table) {
            $table->dropColumn('authority_type');
        });

        Schema::table('authorities', function (Blueprint $table) {
            $table->renameColumn('authority_id', 'subject_id');
        });

        Schema::rename('authorities', 'document_subject');
    }
}

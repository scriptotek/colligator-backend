<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEntitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::update('DELETE FROM entities');
        DB::update('ALTER TABLE entities DROP COLUMN entity_type');
        DB::update('ALTER TABLE entities RENAME TO document_entity');

        Schema::create('entities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();

            $table->string('vocabulary')->nullable();
            $table->string('term');
            $table->string('type');

            // Ideally unique, but could cause sync issues since we're currently 
            // primarily matching on (vocabulary, term)
            $table->string('local_id')->nullable()->index();

            // Not in use yet
            $table->string('uri')->nullable();

            $table->mediumText('extras')->default('{}');

            // ...etc

            $table->index(['vocabulary', 'term']);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('entities');

        DB::update('ALTER TABLE document_entity RENAME TO entities');
        Schema::table('entities', function (Blueprint $table) {
            $table->string('entity_type', 20)->nullable()->index();
        });
    }
}

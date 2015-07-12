<?php

use Colligator\Search\DocumentsIndex;
use Illuminate\Database\Migrations\Migration;

class CreateElasticsearchDocumentsIndex extends Migration
{

    protected $connection = null;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Not sure if we should test ElasticSearch or just mock it
        if (env('APP_ENV') == 'testing') return;
        $docIndex = app('Colligator\Search\DocumentsIndex');
        $docIndex->createVersion(1);
        $docIndex->activateVersion(1);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (env('APP_ENV') == 'testing') return;
        $docIndex = app('Colligator\Search\DocumentsIndex');
        $docIndex->dropVersion(1);
        $docIndex->activateVersion(0);
    }
}

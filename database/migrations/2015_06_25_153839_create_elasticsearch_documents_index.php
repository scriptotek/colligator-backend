<?php

use Colligator\Search\DocumentsIndex;

class CreateElasticsearchDocumentsIndex
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Not sure if we should test ElasticSearch or just mock it
        if (env('APP_ENV') == 'testing') return;
        $se = app('Colligator\Search\DocumentsIndex');
        $se->createVersion(1);
        $se->activateVersion(1);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (env('APP_ENV') == 'testing') return;
        $se = app('Colligator\Search\DocumentsIndex');
        $se->dropVersion(1);
    }
}

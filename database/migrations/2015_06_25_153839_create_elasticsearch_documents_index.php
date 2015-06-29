<?php

use Colligator\SearchEngine;

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
        $se = app('Colligator\SearchEngine');
        $se->createDocumentsIndex();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (env('APP_ENV') == 'testing') return;
        $se = app('Colligator\SearchEngine');
        $se->dropDocumentsIndex();
    }
}

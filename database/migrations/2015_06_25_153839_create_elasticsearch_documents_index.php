<?php

use Shift31\LaravelElasticsearch\Facades\Es;

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
        Es::indices()->create([
            'index' => 'documents'
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (env('APP_ENV') == 'testing') return;
        Es::indices()->delete([
            'index' => 'documents'
        ]);
    }
}

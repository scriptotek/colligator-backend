<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OAI-PMH harvests
    |--------------------------------------------------------------------------
    |
    | Each harvest is configured using the OAI-PMH url, set, and a local
    | collection name stored in the local database.
    |
    */

    'harvests' => [
        'samling42' => [
            'url' => 'http://utvikle-a.bibsys.no/oai/repository',
            'set' => 'urealSamling42',
            'schema' => 'marcxchange'
        ]
    ],

];

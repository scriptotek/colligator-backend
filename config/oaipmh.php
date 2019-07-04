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
            'url'         => 'https://bibsys.alma.exlibrisgroup.com/view/oai/47BIBSYS_UBO/request',
            'set'         => 'ureal_samling42',
            'schema'      => 'marc21',
            'max-retries' => 10,
        ],
        's-litt' => [
            'url'         => 'https://bibsys.alma.exlibrisgroup.com/view/oai/47BIBSYS_UBO/request',
            'set'         => 'S-Litt_Colligator',
            'schema'      => 'marc21',
            'max-retries' => 10,
        ],
        'scifi' => [
            'url'         => 'https://bibsys.alma.exlibrisgroup.com/view/oai/47BIBSYS_UBO/request',
            'set'         => 'ureal_scifi',
            'schema'      => 'marc21',
            'max-retries' => 10,
        ],
    ],

];

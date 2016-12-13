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
            // 'url'         => 'https://sandbox-eu.alma.exlibrisgroup.com/view/oai/47BIBSYS_UBO/request',
            'url'         => 'https://bibsys-k.alma.exlibrisgroup.com/view/oai/47BIBSYS_UBO/request',
            'set'         => 'ureal_samling42',
            'schema'      => 'marc21',
            'max-retries' => 10,
        ],
        's-litt' => [
            'url'         => 'https://bibsys-k.alma.exlibrisgroup.com/view/oai/47BIBSYS_UBO/request',
            'set'         => 'S-Litt_Colligator',
            'schema'      => 'marc21',
            'max-retries' => 10,
        ],
    ],

];

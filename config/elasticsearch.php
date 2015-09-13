<?php

use Monolog\Logger;

return [
    'hosts'    => explode('|', env('ES_HOST')),
    'logPath'  => storage_path('logs/elasticsearch-' . php_sapi_name() . '.log'),
    'logLevel' => Logger::INFO,
];

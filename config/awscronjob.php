<?php

return [
    'connection'        => [
        'region'  => env('AWS_REGION', 'ca-central-1'),
        'version' => env('AWS_API_VERSION', 'latest'),
//        'credentials' => [
//            'key'    => env('AWS_ACCESS_KEY_ID','my-access-key-id'),
//            'secret' => env('AWS_SECRET_ACCESS_KEY','my-secret-access-key'),
//        ],
    ],
    'aws_environment'   => env('AWS_CRON_ENV', 'app-production'),
    'skip_environments' => env('AWS_CRON_SKIP_APP_ENV', 'local'),
    'run_on_errors'     => env('AWS_CRON_RUN_ON_ERRORS', true),
    'cache_time'        => env('AWS_CRON_CACHE_TIME', 5),
];
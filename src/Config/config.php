<?php

return [

    /*
     * Enable or disable the Indexer.
     * If this is set to "null", the app.debug config value will be used.
     */
    'enabled' => env('INDEXER_ENABLED', null),

    /*
     * These tables will be watched by Indexer and specified indexes will be tested.
     */
    'watched_tables' => [
        'users' => ['email'],
    ],
];

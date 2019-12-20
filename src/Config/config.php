<?php

return [

    /*
     * Enable or disable the Indexer.
     */
    'enabled' => env('INDEXER_ENABLED', false),

    /*
     * These tables will be watched by Indexer and specified indexes will be tested.
     */
    'watched_tables' => [
        'users' => [
            // list of already existing indexes to try
            'try_table_indexes' => ['email'],
            // new indexes indexes to try
            'try_indexes' => ['name'],
            // composite indexes to try
            'try_composite_indexes' => [
                ['name', 'email'],
            ],
        ],
    ],
];

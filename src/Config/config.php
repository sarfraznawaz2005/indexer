<?php

return [

    /*
     * Enable or disable the Indexer.
     */
    'enabled' => env('INDEXER_ENABLED', false),

    /*
     * Specify whether to check queries in ajax requests.
     */
    'check_ajax_requests' => true,

    /*
     * These tables will be watched by Indexer and specified indexes will be tested.
     */
    'watched_tables' => [
        /*
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
        */
    ],

    /*
     * These paths/patterns will NOT be handled by Indexer.
     */
    'ignore_paths' => [
        // 'foo',
        // 'foo*',
        // '*foo',
        // '*foo*',
    ],

    /*
     * Outputs results class.
     */
    'output_to' => [
        // outputs results into current visited page.
        Sarfraznawaz2005\Indexer\Outputs\Web::class,
    ],

    /*
     * Font size (including unit) in case of Web output class
     */
    'font_size' => '12px',
];

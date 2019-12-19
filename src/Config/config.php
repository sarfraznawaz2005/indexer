<?php

return [

    /*
     * Enable or disable the query detection.
     * If this is set to "null", the app.debug config value will be used.
     */
    'enabled' => env('QUERY_WATCH_ENABLED', null),


    'watched_tables' => [
        'users' => ['email'],
    ],

    /*
     * Define the output format that you want to use. Multiple classes are supported.
     * Available options are:
     *
     * Alert:
     * Displays an alert on the website
     * Sarfraznawaz2005\QueryWatch\Outputs\Alert::class
     *
     * Console:
     * Writes the N+1 queries into your browsers console log
     * Sarfraznawaz2005\QueryWatch\Outputs\Console::class
     *
     * Clockwork: (make sure you have the itsgoingd/clockwork package installed)
     * Writes the N+1 queries warnings to Clockwork log
     * Sarfraznawaz2005\QueryWatch\Outputs\Clockwork::class
     *
     * Debugbar: (make sure you have the barryvdh/laravel-debugbar package installed)
     * Writes the N+1 queries into a custom messages collector of Debugbar
     * Sarfraznawaz2005\QueryWatch\Outputs\Debugbar::class
     *
     * JSON:
     * Writes the N+1 queries into the response body of your JSON responses
     * Sarfraznawaz2005\QueryWatch\Outputs\Json::class
     *
     * Log:
     * Writes the N+1 queries into the Laravel.log file
     * Sarfraznawaz2005\QueryWatch\Outputs\Log::class
     */
    'output' => [
        Sarfraznawaz2005\QueryWatch\Outputs\Alert::class,
        Sarfraznawaz2005\QueryWatch\Outputs\Log::class,
    ]
];

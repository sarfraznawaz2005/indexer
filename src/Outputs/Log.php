<?php

namespace Sarfraznawaz2005\QueryWatch\Outputs;

use Log as LaravelLog;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class Log implements Output
{
    public function boot()
    {
        //
    }

    public function output(Collection $detectedQueries, Response $response)
    {
        LaravelLog::info('Detected Query');

        foreach ($detectedQueries as $detectedQuery) {
            LaravelLog::info($detectedQuery['sql']);
        }
    }
}

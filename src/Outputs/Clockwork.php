<?php

namespace Sarfraznawaz2005\QueryWatch\Outputs;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class Clockwork implements Output
{
    public function boot()
    {
        //
    }

    public function output(Collection $detectedQueries, Response $response)
    {
        clock()->warning("{$detectedQueries->count()} queries detected:", $detectedQueries->toArray());
    }
}

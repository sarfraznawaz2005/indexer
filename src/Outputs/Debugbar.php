<?php

namespace Sarfraznawaz2005\QueryWatch\Outputs;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

use Barryvdh\Debugbar\Facade as LaravelDebugbar;
use DebugBar\DataCollector\MessagesCollector;

class Debugbar implements Output
{
    protected $collector;

    public function boot()
    {
        $this->collector = new MessagesCollector('Detected Queries');

        if (!LaravelDebugbar::hasCollector($this->collector->getName())) {
            LaravelDebugbar::addCollector($this->collector);
        }
    }

    public function output(Collection $detectedQueries, Response $response)
    {
        foreach ($detectedQueries as $detectedQuery) {
            $this->collector->addMessage($detectedQuery);
        }
    }
}

<?php

namespace Sarfraznawaz2005\Indexer\Outputs;

use Symfony\Component\HttpFoundation\Response;

interface Output
{
    /**
     * @return mixed
     */
    public function boot();

    /**
     * Sends output
     *
     * @param array $queries
     * @param Response $response
     * @return mixed
     */
    public function output(array $queries, Response $response);
}

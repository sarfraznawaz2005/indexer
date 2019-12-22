<?php

namespace Sarfraznawaz2005\Indexer\Outputs;

use Illuminate\Http\Request;
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
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function output(array $queries, Request $request, Response $response);
}

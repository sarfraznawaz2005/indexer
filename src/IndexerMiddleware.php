<?php

namespace Sarfraznawaz2005\Indexer;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IndexerMiddleware
{
    /** @var Indexer */
    private $indexer;

    public function __construct(Indexer $indexer)
    {
        $this->indexer = $indexer;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!$this->indexer->isEnabled()) {
            return $next($request);
        }

        app()->make(Indexer::class);

        /** @var Response $response */
        $response = $next($request);

        $this->indexer->outputResults($response);

        return $response;
    }
}

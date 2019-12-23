<?php

namespace Sarfraznawaz2005\Indexer;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        if ($this->canSendResponse($request, $next($request))) {
            $this->indexer->boot();

            $response = $next($request);

            $this->indexer->outputResults($request, $response);
        }

        return $response ?? $next($request);
    }

    /**
     * See if we can add indexer results to response.
     *
     * @param Request $request
     * @param $response
     * @return bool
     */
    protected function canSendResponse(Request $request, $response): bool
    {
        if (!$this->indexer->isEnabled()) {
            return false;
        }

        if (app()->environment() !== 'local') {
            return false;
        }

        if (!$request->isMethod('get')) {
            return false;
        }

        if ($request->is('*indexer*')) {
            return false;
        }

        if ($response instanceof BinaryFileResponse) {
            return false;
        }

        return true;
    }
}

<?php

namespace Sarfraznawaz2005\QueryWatch;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QueryWatchMiddleware
{
    /** @var QueryWatch */
    private $detector;

    public function __construct(QueryWatch $detector)
    {
        $this->detector = $detector;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! $this->detector->isEnabled()) {
            return $next($request);
        }

        $this->detector->boot();

        /** @var Response $response */
        $response = $next($request);

        // Modify the response to add the Debugbar
        $this->detector->output($request, $response);

        return $response;
    }
}

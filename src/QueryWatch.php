<?php

namespace Sarfraznawaz2005\QueryWatch;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Sarfraznawaz2005\QueryWatch\Events\QueryDetected;
use Symfony\Component\HttpFoundation\Response;

class QueryWatch
{
    private $detectQueries = false;

    /** @var Collection */
    private $queries;

    public function __construct()
    {
        $this->queries = Collection::make();
    }

    public function boot()
    {
        app()->events->listen(QueryExecuted::class, [$this, 'logQuery']);

        foreach ($this->getOutputTypes() as $outputType) {
            app()->singleton($outputType);
            app($outputType)->boot();
        }
    }

    public function isEnabled(): bool
    {
        $configEnabled = value(config('querywatch.enabled'));

        if ($configEnabled === null) {
            $configEnabled = config('app.debug');
        }

        if ($configEnabled) {
            $this->detectQueries = true;
        }

        return $configEnabled;
    }

    public function enableDetection()
    {
        $this->detectQueries = true;
    }

    public function disableDetection()
    {
        $this->detectQueries = false;
    }

    public function isDetecting(): bool
    {
        return $this->detectQueries;
    }


    public function logQuery(QueryExecuted $event)
    {
        $time = $event->time;

        $sql = $this->replaceBindings($event);
        $isSelectQuery = strtolower(strtok($sql, ' ')) === 'select';

        if ($isSelectQuery) {
            $caller = $this->getCallerFromStackTrace();

            $this->queries[] = [
                'connection' => $event->connectionName,
                'sql' => $sql,
                'time' => number_format($time, 2),
                'file' => $caller['file'],
                'line' => $caller['line'],
            ];
        }
    }

    /**
     * Find the first frame in the stack trace outside of Telescope/Laravel.
     *
     * @return array
     */
    protected function getCallerFromStackTrace(): array
    {
        $trace = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))->forget(0);

        return $trace->first(function ($frame) {
            if (!isset($frame['file'])) {
                return false;
            }

            return !Str::contains($frame['file'],
                base_path('vendor' . DIRECTORY_SEPARATOR . $this->ignoredVendorPath())
            );
        });
    }

    /**
     * Choose the frame outside of either Telescope/Laravel or all packages.
     *
     * @return string|null
     */
    protected function ignoredVendorPath()
    {
        if (!($this->options['ignore_packages'] ?? true)) {
            return 'laravel';
        }
    }

    /**
     * Replace the placeholders with the actual bindings.
     *
     * @param QueryExecuted $event
     * @return string
     */
    public function replaceBindings($event): string
    {
        $sql = $event->sql;

        foreach ($this->formatBindings($event) as $key => $binding) {
            $regex = is_numeric($key)
                ? "/\?(?=(?:[^'\\\']*'[^'\\\']*')*[^'\\\']*$)/"
                : "/:{$key}(?=(?:[^'\\\']*'[^'\\\']*')*[^'\\\']*$)/";

            if ($binding === null) {
                $binding = 'null';
            } elseif (!is_int($binding) && !is_float($binding)) {
                $binding = $event->connection->getPdo()->quote($binding);
            }

            $sql = preg_replace($regex, $binding, $sql, 1);
        }

        return $sql;
    }

    /**
     * Format the given bindings to strings.
     *
     * @param QueryExecuted $event
     * @return array
     */
    protected function formatBindings($event): array
    {
        return $event->connection->prepareBindings($event->bindings);
    }

    public function getDetectedQueries(): Collection
    {
        $queries = $this->queries->values();

        if ($queries->isNotEmpty()) {
            event(new QueryDetected($queries));
        }

        return $queries;
    }

    protected function getOutputTypes()
    {
        $outputTypes = config('querywatch.output');

        if (!is_array($outputTypes)) {
            $outputTypes = [$outputTypes];
        }

        return $outputTypes;
    }

    protected function applyOutput(Response $response)
    {
        foreach ($this->getOutputTypes() as $type) {
            app($type)->output($this->getDetectedQueries(), $response);
        }
    }

    public function output($request, $response)
    {
        if ($this->getDetectedQueries()->isNotEmpty()) {
            $this->applyOutput($response);
        }

        return $response;
    }
}

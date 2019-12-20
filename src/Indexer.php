<?php

namespace Sarfraznawaz2005\Indexer;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Indexer
{
    private $detectQueries = false;

    private static $counter;

    /** @var Collection */
    private $queries;

    public function __construct()
    {
        $this->queries = Collection::make();

        @unlink(__DIR__ . '/log.html');
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

            $hasChance = $this->tryIndexes($sql, $event);

            if ($hasChance) {
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
    }

    protected function tryIndexes(string $sql, QueryExecuted $event)
    {
        $table = trim(str_ireplace(['from', '`'], '', stristr($sql, 'from')));
        $table = strtok($table, ' ');

        if (array_key_exists($table, config('querywatch.watched_tables', []))) {
            $indexes = config("querywatch.watched_tables.$table", []);

            // todo: index combinations

            foreach ($indexes as $index) {
                if (!$this->indexExists($table, $index)) {
                    $this->addIndex($table, $index);
                }

                $this->explainQuery($sql, $event);

                $this->removeIndex($table, $index);
            }
        }
    }

    protected function indexExists($table, $index): bool
    {
        $indexes = collect(DB::select("SHOW INDEXES FROM $table"))->pluck('Key_name')->toArray();

        return in_array($index, $indexes, true);
    }

    protected function addIndex($table, $index)
    {
        try {
            Schema::table($table, static function (Blueprint $table) use ($index) {
                $table->index([$index]);
            });
        } catch (\Exception $e) {

        }
    }

    protected function removeIndex($table, $index)
    {
        try {
            Schema::table($table, static function (Blueprint $table) use ($index) {
                $table->dropIndex([$index]);
            });
        } catch (\Exception $e) {

        }
    }

    protected function explainQuery(string $sql, QueryExecuted $event)
    {
        //dump($sql);

        self::$counter++;

        $output = '<strong>' . (self::$counter) . ' - ' . number_format($event->time, 2) . 'ms</strong><br>' . SqlFormatter::format($sql);

        $result = DB::select(DB::raw('EXPLAIN ' . $sql));

        if (isset($result[0])) {
            $output .= $this->table([(array)$result[0]]);
        }

        $output = '<div style="background: #F7F0CB; margin: 0 20px 0 20px; overflow:auto; color:#000; padding: 5px; width:auto;">' . $output . '</div>';

        file_put_contents(__DIR__ . '/log.html', $output, FILE_APPEND);
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

    protected function table($data): string
    {
        $keys = array_keys(end($data));
        $size = array_map('strlen', $keys);

        foreach (array_map('array_values', $data) as $e) {
            $size = array_map('max', $size,
                array_map('strlen', $e));
        }

        foreach ($size as $n) {
            $form[] = "%-{$n}s";
            $line[] = str_repeat('-', $n);
        }

        $form = '| ' . implode(' | ', $form) . " |\n";
        $line = '+-' . implode('-+-', $line) . "-+\n";
        $rows = array(vsprintf($form, $keys));

        foreach ($data as $e) {
            $rows[] = vsprintf($form, $e);
        }

        return "<pre>\n" . $line . implode($line, $rows) . $line . "</pre>\n";
    }
}

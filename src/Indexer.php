<?php

namespace Sarfraznawaz2005\Indexer;

use Exception;
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Sarfraznawaz2005\Indexer\Outputs\Web;
use Sarfraznawaz2005\Indexer\Traits\FetchesStackTrace;

class Indexer
{
    use FetchesStackTrace;

    protected $detectQueries = false;

    /** @var QueryExecuted */
    protected $queryEvent;

    protected $table = '';

    protected $queries = [];

    protected $source = [];

    protected $unremovedIndexes = [];

    /**
     * Indexer constructor.
     */
    public function __construct()
    {
        if ($this->isEnabled() && $this->isDetecting()) {

            app()->events->listen(QueryExecuted::class, [$this, 'analyzeQuery']);

            foreach ($this->getOutputTypes() as $outputType) {
                app()->singleton($outputType);
                app($outputType)->boot();
            }
        }
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        $configEnabled = config('indexer.enabled', false);

        if ($configEnabled) {
            $this->detectQueries = true;
        }

        return $configEnabled;
    }

    /**
     * Enable Indexer
     */
    public function enableDetection()
    {
        $this->detectQueries = true;
    }

    /**
     * Disable Indexer
     */
    public function disableDetection()
    {
        $this->detectQueries = false;
    }

    /**
     * Checks if Indexer is watching queries.
     *
     * @return bool
     */
    public function isDetecting(): bool
    {
        return $this->detectQueries;
    }

    /**
     * Starts indexing process.
     *
     * @param QueryExecuted $event
     */
    public function analyzeQuery(QueryExecuted $event)
    {
        if (!$this->isDetecting()) {
            return;
        }

        $this->queryEvent = $event;
        $this->table = $this->getTableNameFromQuery();
        $this->source = $this->getCallerFromStackTrace();

        if ($this->isSelectQuery()) {
            $this->disableDetection();
            $this->tryIndexes();
            $this->enableDetection();
        }
    }

    /**
     * Get current SQL query.
     *
     * @return string
     */
    protected function getSql(): string
    {
        return $this->replaceBindings($this->queryEvent);
    }

    /**
     * Gets current query model name.
     *
     * @return string
     */
    protected function getModelName(): string
    {
        $backtrace = collect(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 50));

        $modelTrace = $backtrace->first(static function ($trace) {
            return Arr::get($trace, 'object') instanceof Builder;
        });

        return $modelTrace ? get_class($modelTrace['object']->getModel()) : '';
    }

    /**
     * @return bool
     */
    protected function isSelectQuery(): bool
    {
        return strtolower(strtok($this->getSql(), ' ')) === 'select';
    }

    /**
     * @return string
     */
    protected function getTableNameFromQuery(): string
    {
        $table = trim(str_ireplace(['from', '`'], '', stristr($this->getSql(), 'from')));

        return strtok($table, ' ');
    }

    /**
     * Tries applying given indexes.
     */
    protected function tryIndexes()
    {
        $addedIndexes = [];
        $addedIndexesComposite = [];

        $table = $this->table;

        if (array_key_exists($table, config('indexer.watched_tables', []))) {
            $tableIndexeOptions = config("indexer.watched_tables.$table", []);

            $tableOriginalIndexes = $this->getTableOriginalIndexes();
            $tableIndexes = $tableIndexeOptions['try_table_indexes'] ?? [];
            $newIndexes = $tableIndexeOptions['try_indexes'] ?? [];
            $compositeIndexes = $tableIndexeOptions['try_composite_indexes'] ?? [];

            $indexes = array_merge($tableIndexes, $newIndexes);

            try {

                // try individual indexes
                $addedIndexes = $this->applyIndexes($indexes);

                // remove any custom added indexes
                $this->removeUserDefinedIndexes($addedIndexes);

                // try composite indexes
                $addedIndexesComposite = $this->applyIndexes($compositeIndexes);

            } catch (Exception $e) {

            } finally {
                // just in case - again remove any custom added indexes
                $this->removeUserDefinedIndexes($addedIndexes);
                $this->removeUserDefinedIndexes($addedIndexesComposite);

                // make sure we have deleted added indexes
                $this->unremovedIndexes = $this->checkAnyUnremovedIndexes($tableOriginalIndexes);
            }
        }
    }

    /**
     * Gets already applied indexes on the table.
     *
     * @return array
     */
    protected function getTableOriginalIndexes(): array
    {
        $table = $this->table;

        return collect(DB::select("SHOW INDEXES FROM $table"))->pluck('Key_name')->toArray();
    }

    /**
     * Checks if we have any un-removed indexes after applying indexes.
     *
     * @param $tableOriginalIndexes
     * @return array
     */
    protected function checkAnyUnremovedIndexes($tableOriginalIndexes): array
    {
        $tableOriginalIndexesAfter = $this->getTableOriginalIndexes();

        if ($tableOriginalIndexes !== $tableOriginalIndexesAfter) {
            return array_diff($tableOriginalIndexesAfter, $tableOriginalIndexes);
        }

        return [];
    }

    /**
     * Applies given indexes to the table, builds EXPLAIN query and then removes added indexes.
     *
     * @param array $indexes
     * @return array
     */
    protected function applyIndexes(array $indexes): array
    {
        $addedIndexes = [];

        foreach ($indexes as $index) {
            if (!$this->indexExists($index)) {
                $addedIndexes[] = $index;

                $this->addIndex($index);
                $this->explainQuery($index);
                $this->removeIndex($index);

            } else {
                $this->explainQuery($index);
            }
        }

        return $addedIndexes;
    }

    /**
     * @param $index
     * @return bool
     */
    protected function indexExists($index): bool
    {
        $table = $this->table;

        $indexes = collect(DB::select("SHOW INDEXES FROM $table"))->pluck('Key_name')->toArray();

        return in_array($index, $indexes, true);
    }

    /**
     * @param $index
     */
    protected function addIndex($index)
    {
        $table = $this->table;

        try {
            Schema::table($table, static function (Blueprint $table) use ($index) {
                $table->index($index);
            });
        } catch (Exception $e) {

        }
    }

    /**
     * @param $index
     */
    protected function removeIndex($index)
    {
        $table = $this->table;

        try {
            Schema::table($table, static function (Blueprint $table) use ($index) {

                is_array($index) ? $table->dropIndex($index) : $table->dropIndex([$index]);

            });
        } catch (Exception $e) {

        }
    }

    /**
     * Removes indexes given in config eg not ones already applied on the table.
     *
     * @param $addedIndexes
     */
    protected function removeUserDefinedIndexes($addedIndexes)
    {
        foreach ($addedIndexes as $index) {
            $this->removeIndex($index);
        }
    }

    /**
     * Collects EXPLAIN info and stores in queries var.
     *
     * @param $index
     */
    protected function explainQuery($index)
    {
        $event = $this->queryEvent;
        $sql = $this->getSql();

        $result = DB::select(DB::raw('EXPLAIN ' . $sql))[0] ?? null;

        if ($result) {
            $result = (array)$result;
            $result['sql'] = $sql;
            $result['time'] = number_format($event->time, 2);
            $result['index_name'] = $this->getLaravelIndexName($index);
            $result['file'] = $this->source['file'];
            $result['line'] = $this->source['line'];

            $this->queries[] = $result;
        }
    }

    /**
     * Gets composite indexes name based on how Laravel makes those names by default.
     *
     * @param string|array $index
     * @return string
     */
    protected function getLaravelIndexName($index): string
    {
        $name[] = $this->table;

        if (!is_array($index)) {
            $name[] = $index;
        } else {
            foreach ($index as $indexItem) {
                $name[] = $indexItem;
            }
        }

        $name[] = 'index';

        return strtolower(implode('_', $name));
    }

    /**
     * Replace the placeholders with the actual bindings.
     *
     * @param QueryExecuted $event
     * @return string
     */
    protected function replaceBindings($event): string
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

    /**
     * @return array|Repository|mixed
     */
    protected function getOutputTypes()
    {
        $outputTypes = config('indexer.output_to', [Web::class]);

        if (!is_array($outputTypes)) {
            $outputTypes = [$outputTypes];
        }

        return $outputTypes;
    }

    /**
     * Applies output.
     *
     * @param \Symfony\Component\HttpFoundation\Response $response
     */
    protected function applyOutput(\Symfony\Component\HttpFoundation\Response $response)
    {
        foreach ($this->getOutputTypes() as $type) {
            app($type)->output($this->queries, $response);
        }
    }

    /**
     * Outputs results.
     *
     * @param Response $response
     * @return Response|void
     */
    public function outputResults(Response $response)
    {
        if (!$this->queries) {
            return;
        }

        $this->applyOutput($response);

        return $response;
    }
}

<?php

namespace Sarfraznawaz2005\Indexer;

use Exception;
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Sarfraznawaz2005\Indexer\Outputs\Web;
use Sarfraznawaz2005\Indexer\Traits\FetchesStackTrace;

class Indexer
{
    use FetchesStackTrace;

    protected $detectQueries = true;

    /** @var QueryExecuted */
    protected $queryEvent;

    protected $table = '';

    protected $queries = [];

    protected $source = [];

    protected $skippedTables = [];

    protected $unremovedIndexes = [];

    /**
     * Starts things up.
     */
    public function boot()
    {
        if (!$this->isEnabled()) {
            return false;
        }

        app()->events->listen(QueryExecuted::class, [$this, 'analyzeQuery']);

        foreach ($this->getOutputTypes() as $outputType) {
            app()->singleton($outputType);
            app($outputType)->boot();
        }
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return config('indexer.enabled', false);
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
        //dump($this->table);

        if ($this->isAllowedRequest() && $this->isSelectQuery()) {
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
        $table = strtok($table, ' ');

        return preg_replace('/\v(?:[\v\h]+)/', '', $table);
    }

    /**
     * Check if we are handling allowed request/page.
     *
     * @return bool
     */
    protected function isAllowedRequest(): bool
    {
        $ignoredPaths = array_merge([
            '*indexer*',
            '*meter*',
            '*debugbar*',
            '*_debugbar*',
            '*clockwork*',
            '*_clockwork*',
            '*telescope*',
            '*horizon*',
            '*vendor/horizon*',
            '*nova-api*',
        ], config('indexer.ignore_paths', []));

        return !app()->request->is($ignoredPaths);
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
                dump('Indexer Error: ' . $e->getMessage());
            } finally {
                // just in case - again remove any custom added indexes
                $this->removeUserDefinedIndexes($addedIndexes);
                $this->removeUserDefinedIndexes($addedIndexesComposite);

                // make sure we have deleted added indexes
                $this->unremovedIndexes = $this->checkAnyUnremovedIndexes($tableOriginalIndexes);
            }
        } else {
            $this->skippedTables[] = $table;
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

            $key = $this->makeKey($index);

            // don't do anything if we have already checked this query
            if (array_key_exists($key, $this->queries)) {
                continue;
            }

            if (!$this->indexExists($index)) {
                $addedIndexes[] = $index;

                $this->addIndex($index);
                $this->explainQuery($index, false);
                $this->removeIndex($index);

            } else {
                $this->explainQuery($index, true);
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

        if (!in_array($index, $indexes, true)) {
            $index = $this->getLaravelIndexName($index);
        }

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
            dump('Indexer Error: ' . $e->getMessage());
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
                @$table->dropIndex($this->getLaravelIndexName($index));

                is_array($index) ? $table->dropIndex([$index]) : $table->dropIndex($index);
            });
        } catch (Exception $e) {
            dump('Indexer Error: ' . $e->getMessage());
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
     * @param $isIndexAlreadyPresentOnTable
     */
    protected function explainQuery($index, $isIndexAlreadyPresentOnTable)
    {
        $indexType = $isIndexAlreadyPresentOnTable ? 'Already Present On Table' : 'Added By Indexer';
        $indexName = is_array($index) ? $this->getLaravelIndexName($index) : $index;
        $indexName = "$indexName ($indexType)";

        $event = $this->queryEvent;

        $sql = $this->getSql();
        $hints = $this->performQueryAnalysis($sql);

        $result = DB::select(DB::raw('EXPLAIN ' . $sql))[0] ?? null;

        if ($result) {
            $queryResult['explain_result'] = (array)$result;
            $queryResult['sql'] = $sql;
            $queryResult['time'] = number_format($event->time, 2) . 'ms';
            $queryResult['index_name'] = $indexName;
            $queryResult['file'] = $this->source['file'];
            $queryResult['line'] = $this->source['line'];
            $queryResult['hints'] = $hints;
            $queryResult['skippedTables'] = $this->skippedTables;

            $this->queries[$this->makeKey($indexName)] = $queryResult;
        }
    }

    /**
     * Makes queries array keys so we don't analyze same query again.
     *
     * @param $indexName
     * @return string
     */
    protected function makeKey($indexName): string
    {
        return md5($this->getLaravelIndexName($indexName) . $this->getSql());
    }

    /**
     * Explainer::performQueryAnalysis()
     *
     * Perform simple regex analysis on the code
     *
     * @package xplain (https://github.com/rap2hpoutre/mysql-xplain-xplain)
     * @author e-doceo
     * @copyright 2014
     * @version $Id$
     * @access public
     * @param string $query
     * @return array
     */
    protected function performQueryAnalysis($query): array
    {
        $hints = [];

        if (preg_match('/^\\s*SELECT\\s*`?[a-zA-Z0-9]*`?\\.?\\*/i', $query)) {
            $hints[] = 'Use <code>SELECT *</code> only if you need all columns from table';
        }

        if (preg_match('/ORDER BY RAND()/i', $query)) {
            $hints[] = '<code>ORDER BY RAND()</code> is slow, try to avoid if you can.
				You can <a href="http://stackoverflow.com/questions/2663710/how-does-mysqls-order-by-rand-work" target="_blank">read this</a>
				or <a href="http://stackoverflow.com/questions/1244555/how-can-i-optimize-mysqls-order-by-rand-function" target="_blank">this</a>';
        }

        if (strpos($query, '!=') !== false) {
            $hints[] = 'The <code>!=</code> operator is not standard. Use the <code>&lt;&gt;</code> operator to test for inequality instead.';
        }

        if (stripos($query, 'WHERE') === false && preg_match('/^(SELECT) /i', $query)) {
            $hints[] = 'The <code>SELECT</code> statement has no <code>WHERE</code> clause and could examine many more rows than intended';
        }

        if (preg_match('/LIMIT\\s/i', $query) && stripos($query, 'ORDER BY') === false) {
            $hints[] = '<code>LIMIT</code> without <code>ORDER BY</code> causes non-deterministic results, depending on the query execution plan';
        }

        if (preg_match('/LIKE\\s[\'"](%.*?)[\'"]/i', $query, $matches)) {
            $hints[] = 'An argument has a leading wildcard character: <code>' . $matches[1] . '</code>.
								The predicate with this argument is not sargable and cannot use an index if one exists.';
        }

        return $hints;
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
     * @param Request $request
     * @param \Symfony\Component\HttpFoundation\Response $response
     */
    protected function applyOutput(Request $request, \Symfony\Component\HttpFoundation\Response $response)
    {
        foreach ($this->getOutputTypes() as $type) {
            app($type)->output($this->queries, $request, $response);
        }
    }

    /**
     * Outputs results.
     *
     * @param Request $request
     * @param $response
     * @return Response|void
     */
    public function outputResults(Request $request, $response)
    {
        // we don't modify json response
        if ($request->expectsJson()) {
            file_put_contents(storage_path('indexer.json'), json_encode($this->queries));

            return;
        }

        $this->applyOutput($request, $response);

        return $response;
    }
}

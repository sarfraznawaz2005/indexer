<?php

namespace Sarfraznawaz2005\Indexer;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Indexer
{
    protected $detectQueries = false;

    /** @var QueryExecuted */
    protected $queryEvent;

    protected $table = '';

    protected $queries = [];

    protected $unremovedIndexes = [];

    /**
     * Indexer constructor.
     */
    public function __construct()
    {
        if ($this->isEnabled() && $this->isDetecting()) {
            app()->events->listen(QueryExecuted::class, [$this, 'analyzeQuery']);
            app()->events->listen(RequestHandled::class, [$this, 'outputResults']);
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

        $result = DB::select(DB::raw('EXPLAIN ' . $sql))[0];

        if ($result) {
            $result = (array)$result;
            $result['sql'] = $sql;
            $result['time'] = number_format($event->time, 2);
            $result['index_name'] = $this->getLaravelIndexName($index);

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

    /**
     * Outputs findings to current page.
     */
    public function outputResults()
    {
        if (!$this->queries) {
            return;
        }

        $output = <<< OUTOUT
            <style>
                .indexer_query_info, .indexer_query_info:hover { position:fixed; z-index:999999999999; bottom:35px; right:35px; padding:5px 10px; font-size:20px; border-radius:5px;color:#333; text-decoration: none; }
                .indexer_query_info .number { font-weight: bold; font-size: 24px; }
                .indexer pre { color:#000 !important; padding:10px; !important; margin:0 !important; }
                .indexer { width:100%; height:100%; position: fixed; background: #edf1f3; top:0; left:0; color:#000; padding:25px; z-index:999999999; margin:0; overflow:auto; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif !important; font-size:1rem !important; }
                .indexer_section { background: #fff; margin:0 0 20px 0; border:1px solid #dae0e5; border-radius:5px; }
                .indexer_section_details { padding:5px; font-size:.90rem; }
                .indexer .left { float: left; }
                .indexer .right { float: right; }
                .indexer .clear { clear: both; }
                .indexer .error { background:#ff6586; color:#fff; font-weight:bold; text-align:center; border:1px solid red; padding:10px; margin:10px 0;}
            </style>
OUTOUT;

        $optimizationsCount = 0;

        foreach ($this->queries as $query) {
            if ($query['possible_keys']) {
                $optimizationsCount++;
            }
        }

        $bgColor = $optimizationsCount ? 'lightgreen' : '#fff382';

        $output .= '<a href="#" class="indexer_query_info" style="background: ' . $bgColor . '">INDEXER: <span class="number">' . $optimizationsCount . '</span></a>';

        $output .= '<div class="indexer" style="display: none;">';

        foreach ($this->queries as $query) {

            $bgColor = $query['possible_keys'] ? '#a2e5b1' : '#dae0e5';

            $output .= '<div class="indexer_section">';
            $output .= '<div class="indexer_section_details" style="background: ' . $bgColor . '">';
            $output .= "<div class='left'>Index: <strong>$query[index_name]</strong></div>";
            $output .= "<div class='right'>Time: <strong>$query[time]</strong></div>";
            $output .= "<div class='clear'></div>";
            $output .= '</div>';
            $output .= SqlFormatter::highlight($query['sql']);
            $output .= $this->table([array_slice($query, 0, -3)]);
            $output .= '</div>';
        }

        if ($this->unremovedIndexes) {
            $output .= '<div class="error">Following indexes could not be removed, please remove them manually:</div>';
            $output .= implode('<br> - ', $this->unremovedIndexes);
        }

        $output .= '<div style="margin:0 0 100px 0 !important;"></div>';
        $output .= '</div>';

        $output .= <<< OUTOUT
        <script>
            document.querySelector(".indexer_query_info").addEventListener("click", function(e) {
                var indexer = document.querySelector(".indexer");
                e.preventDefault();
                
                indexer.style.display = indexer.style.display === "none" ? "block" : "none";
            });
        </script>
OUTOUT;

        echo $output;
    }
}

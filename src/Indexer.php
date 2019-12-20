<?php

namespace Sarfraznawaz2005\Indexer;

use Exception;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Indexer
{
    protected $detectQueries = false;

    /** @var QueryExecuted */
    protected $queryEvent;

    protected $table = '';

    protected $queries = [];

    public function __construct()
    {
        if ($this->isEnabled() && $this->isDetecting()) {
            app()->events->listen(QueryExecuted::class, [$this, 'analyzeQuery']);
            app()->events->listen(RequestHandled::class, [$this, 'outputResults']);
        }
    }

    public function isEnabled(): bool
    {
        $configEnabled = value(config('indexer.enabled'));

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

    public function analyzeQuery(QueryExecuted $event)
    {
        if (!$this->isDetecting()) {
            return;
        }

        $this->queryEvent = $event;
        $this->table = $this->getTableNameFromQuery();

        if ($this->isSelectQuery()) {
            $this->tryIndexes();
        }
    }

    protected function getSql(): string
    {
        return $this->replaceBindings($this->queryEvent);
    }

    protected function isSelectQuery(): bool
    {
        return strtolower(strtok($this->getSql(), ' ')) === 'select';
    }

    protected function getTableNameFromQuery(): string
    {
        $sql = strtolower($this->getSql());

        $table = trim(str_ireplace(['from', '`'], '', stristr($sql, 'from')));

        return strtok($table, ' ');
    }

    protected function tryIndexes()
    {
        $addedIndexes = [];
        $addedIndexesComposite = [];

        $this->disableDetection();

        $table = $this->table;

        if (array_key_exists($table, config('indexer.watched_tables', []))) {
            $tableIndexeOptions = config("indexer.watched_tables.$table", []);

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
                // remove any custom added indexes
                $this->removeUserDefinedIndexes($addedIndexes);
                $this->removeUserDefinedIndexes($addedIndexesComposite);
            }
        }

        $this->enableDetection();
    }

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

    protected function indexExists($index): bool
    {
        $table = $this->table;

        $indexes = collect(DB::select("SHOW INDEXES FROM $table"))->pluck('Key_name')->toArray();

        return in_array($index, $indexes, true);
    }

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

    protected function removeUserDefinedIndexes($addedIndexes)
    {
        foreach ($addedIndexes as $index) {
            $this->removeIndex($index);
        }
    }

    protected function explainQuery($index)
    {
        $event = $this->queryEvent;
        $sql = $this->getSql();

        $result = DB::select(DB::raw('EXPLAIN ' . $sql))[0];

        if ($result) {

            if (is_array($index)) {
                $index = $this->getCompositeIndexName($index);
            }

            $result = (array)$result;
            $result['sql'] = $sql;
            $result['time'] = number_format($event->time, 2);
            $result['index_name'] = $index;

            $this->queries[] = $result;
        }
    }

    /**
     * Gets composite indexes name based on how Laravel makes those names by default.
     *
     * @param array $compositeIndexes
     * @return string
     */
    protected function getCompositeIndexName(array $compositeIndexes): string
    {
        $name[] = $this->table;

        foreach ($compositeIndexes as $index) {
            $name[] = $index;
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

    public function outputResults()
    {
        $output = '';

        if ($this->queries) {
            $output .= '<style>';
            $output .= 'body,html { line-height:100% !important; background: #edf1f3 !important; }';
            $output .= 'pre { color:#000 !important; padding:10px; !important; margin:0 !important; }';
            $output .= '.indexer { width:100%; height:100%; position: fixed; background: #edf1f3; top:0; left:0; color:#000; padding:25px; z-index:999999999; margin:0 0 100px 0; overflow:auto; }';
            $output .= '.indexer_section { background: #fff; margin:0 0 20px 0; border:1px solid #dae0e5; border-radius:5px; }';
            $output .= '.indexer_section_details { padding:5px; font-size:.90rem; }';
            $output .= '.left { float: left; }';
            $output .= '.right { float: right; }';
            $output .= '.clear { clear: both; }';
            $output .= '</style>';

            $output .= '<div class="indexer">';

            foreach ($this->queries as $query) {

                $bgColor = $query['possible_keys'] ? '#a2e5b1' : '#dae0e5';

                $output .= '<div class="indexer_section">';
                $output .= '<div class="indexer_section_details" style="background: ' . $bgColor . '">';
                $output .= "<div class='left'>Index: $query[index_name]</div>";
                $output .= "<div class='right'>Time: $query[time]</div>";
                $output .= "<div class='clear'></div>";
                $output .= '</div>';
                $output .= SqlFormatter::highlight($query['sql']);
                $output .= $this->table([array_slice($query, 0, -3)]);
                $output .= '</div>';
            }

            $output .= '</div>';
        }

        echo $output;
    }
}

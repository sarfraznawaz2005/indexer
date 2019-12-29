<?php

if (!function_exists('indexerOptimizedKey')) {
    /**
     * Decides if query is optimized
     *
     * @param array $query
     * @return string
     */
    function indexerOptimizedKey(array $query): string
    {
        if (function_exists('indexerOptimizedKeyCustom')) {
            return indexerOptimizedKeyCustom($query);
        }

        return trim($query['explain_result']['key']);
    }
}

if (!function_exists('indexerGetOptimizedCount')) {
    /**
     * Gets total count of optimized queries
     *
     * @param array $queries
     * @return string
     */
    function indexerGetOptimizedCount(array $queries): string
    {
        $count = 0;

        foreach ($queries as $query) {
            if (!$query['slow'] && indexerOptimizedKey($query)) {
                $count++;
            }
        }

        return $count;
    }
}

if (!function_exists('indexerGetSlowCount')) {
    /**
     * Gets total count of slow queries
     *
     * @param array $queries
     * @return string
     */
    function indexerGetSlowCount(array $queries): string
    {
        $count = 0;

        foreach ($queries as $query) {
            if ($query['slow']) {
                $count++;
            }
        }

        return $count;
    }
}

if (!function_exists('indexerMakeExplainResults')) {
    /**
     * Creates result section.
     *
     * @param array $queries
     * @return string
     */
    function indexerMakeExplainResults(array $queries): string
    {
        $output = '';

        foreach ($queries as $query) {

            $sectionClass = indexerOptimizedKey($query) ? 'optimized' : 'normal';

            if ($query['slow']) {
                $sectionClass = 'slow';
            }

            $output .= "<div class='indexer_section'>";
            $output .= "<div class='indexer_section_details $sectionClass'>";
            $output .= "<div class='left'><strong>$query[title]</strong></div>";
            $output .= "<div class='right'><strong>$query[time]</strong></div>";
            $output .= "<div class='clear'></div>";
            $output .= '</div>';
            $output .= "<div class='padded'>";
            $output .= "File: $query[file]<br>";
            $output .= "Line: $query[line]";
            $output .= '</div>';
            $output .= '<div class="sql">' . $query['sql'] . '</div>';
            $output .= indexerTable([$query['explain_result']]);

            if ($query['hints']) {
                $output .= "<div class='padded'>";

                foreach ($query['hints'] as $hint) {
                    $output .= "<span class='hint'>Hint</span> $hint<br>";
                }

                $output .= '</div>';
            }

            $output .= '</div>';
        }

        return $output;
    }
}

if (!function_exists('indexerTable')) {
    /**
     * Generates HTML table.
     *
     * @param $array
     * @param bool $table
     * @return string
     */
    function indexerTable($array, $table = true): string
    {
        $out = '';

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (!isset($tableHeader)) {
                    $tableHeader = '<th>' . implode('</th><th>', array_keys($value)) . '</th>';
                }

                array_keys($value);

                $out .= '<tr>';
                $out .= indexerTable($value, false);
                $out .= '</tr>';
            } else {
                $out .= "<td>$value</td>";
            }
        }

        if ($table) {
            return '<table class="indexer_table">' . $tableHeader . $out . '</table>';
        }

        return $out;
    }
}


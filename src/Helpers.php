<?php

if (!function_exists('indexerOptimizedKey')) {
    function indexerOptimizedKey(array $query): string
    {
        return trim($query['explain_result']['key']);
    }
}

if (!function_exists('indexerGetOptimizedCount')) {
    function indexerGetOptimizedCount(array $queries): string
    {
        $optimizationsCount = 0;

        foreach ($queries as $query) {
            if (indexerOptimizedKey($query)) {
                $optimizationsCount++;
            }
        }

        return $optimizationsCount;
    }
}

if (!function_exists('makeExplainResults')) {
    function makeExplainResults(array $queries): string
    {
        $output = '';

        foreach ($queries as $query) {

            $bgColor = indexerOptimizedKey($query) ? '#91e27f' : '#dae0e5';

            $output .= '<div class="indexer_section">';
            $output .= '<div class="indexer_section_details" style="background: ' . $bgColor . '">';
            $output .= "<div class='left'><strong>$query[index_name]</strong></div>";
            $output .= "<div class='right'><strong>$query[time]</strong></div>";
            $output .= "<div class='clear'></div>";
            $output .= '</div>';
            $output .= "<div class='padded'>";
            $output .= "File: <strong>$query[file]</strong><br>";
            $output .= "Line: <strong>$query[line]</strong>";
            $output .= '</div>';
            $output .= '<div class="sql"><pre>' . $query['sql'] . '</pre></div>';
            $output .= explainToTable([$query['explain_result']]);

            if ($query['hints']) {
                $output .= "<div class='padded'>";

                foreach ($query['hints'] as $hint) {
                    $output .= "<span class='hint'>Hint</span> <strong>$hint</strong><br>";
                }

                $output .= '</div>';
            }

            $output .= '</div>';
        }

        return $output;
    }
}

if (!function_exists('explainToTable')) {
    function explainToTable($data): string
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


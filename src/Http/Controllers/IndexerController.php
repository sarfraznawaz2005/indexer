<?php

namespace Sarfraznawaz2005\Indexer\Http\Controllers;

class IndexerController
{
    public function __invoke()
    {
        $output = '';
        $path = storage_path('indexer.json');

        if (file_exists($path)) {
            if ($contents = file_get_contents($path)) {

                $queries = json_decode($contents, true);

                if ($queries) {

                    $output .= '<div class="padded"><strong>Added from Ajax Request(s):</strong></div>';
                    $output .= makeExplainResults($queries);

                    $totalQueries = count($queries);
                    $optimizationsCount = indexerGetOptimizedCount($queries);

                    return [
                        'key' => md5($output . $optimizationsCount . $totalQueries),
                        'content' => $output,
                        'counts' => [
                            'optimized' => $optimizationsCount,
                            'total' => $totalQueries,
                        ],
                    ];
                }
            }
        }

        return '';
    }
}

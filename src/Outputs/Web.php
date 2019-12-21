<?php

namespace Sarfraznawaz2005\Indexer\Outputs;

use Sarfraznawaz2005\Indexer\SqlFormatter;
use Symfony\Component\HttpFoundation\Response;

class Web implements Output
{
    public function boot()
    {
        //
    }

    public function output(array $queries, Response $response)
    {
        if ($response->isRedirection() || stripos($response->headers->get('Content-Type'), 'text/html') !== 0) {
            return;
        }

        $content = $response->getContent();

        $outputContent = $this->getOutputContent($queries);

        $pos = strripos($content, '</body>');

        if (false !== $pos) {
            $content = substr($content, 0, $pos) . $outputContent . substr($content, $pos);
        } else {
            $content .= $outputContent;
        }

        // Update the new content and reset the content length
        $response->setContent($content);

        $response->headers->remove('Content-Length');
    }

    /**
     * Sends output
     *
     * @param array $queries
     * @return string
     */
    protected function getOutputContent(array $queries): string
    {
        $queryFormatMethod = config('indexer.format_queries', false) ? 'format' : 'highlight';

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

        foreach ($queries as $query) {
            if ($query['possible_keys']) {
                $optimizationsCount++;
            }
        }

        $bgColor = $optimizationsCount ? 'lightgreen' : '#fff382';

        $output .= '<a href="#" class="indexer_query_info" style="background: ' . $bgColor . '">INDEXER: <span class="number">' . $optimizationsCount . '</span></a>';

        $output .= '<div class="indexer" style="display: none;">';

        foreach ($queries as $query) {

            $bgColor = $query['possible_keys'] ? '#a2e5b1' : '#dae0e5';

            $output .= '<div class="indexer_section">';
            $output .= '<div class="indexer_section_details" style="background: ' . $bgColor . '">';
            $output .= "<div class='left'><strong>$query[index_name]</strong></div>";
            $output .= "<div class='right'><strong>$query[time]</strong></div>";
            $output .= "<div class='clear'></div>";
            $output .= '</div>';
            $output .= SqlFormatter::$queryFormatMethod($query['sql']);
            $output .= $this->table([array_slice($query, 0, -3)]);
            $output .= '</div>';
        }

        $output .= '<div style="margin:0 0 75px 0 !important;"></div>';
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

        return $output;
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

<?php

namespace Sarfraznawaz2005\Indexer\Outputs;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Web implements Output
{
    public function boot()
    {
        //
    }

    public function output(array $queries, Request $request, Response $response)
    {
        if ($response->isRedirection() || stripos($response->headers->get('Content-Type'), 'text/html') !== 0) {
            return;
        }

        if ($response->isRedirection()) {
            return;
        }

        if (app()->runningInConsole()) {
            return;
        }

        if (!$request->acceptsHtml()) {
            return;
        }

        $content = $response->getContent();

        $pos = strripos($content, '</body>');

        $outputContent = $this->getOutputContent($queries);

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
        $output = '<!--start_indexer_response-->';

        $output .= <<< OUTOUT
            <style>
                 html { font-size: 1rem !important; }
                .indexer_query_info, .indexer_query_info:active .indexer_query_info:visited .indexer_query_info:hover { position:fixed !important; z-index:2147483647 !important; bottom:20px !important; right:45px !important; padding: 2px 10px 5px 10px !important; font-size:20px !important; border-radius:5px !important;color:#333 !important; text-decoration: none !important; }
                .indexer_query_info .number { font-weight: bold !important; font-size: 24px !important; }
                .indexer_alert { background: #a1ff8e !important; padding:2px 5px !important; border-radius: 5px !important; position:fixed !important; z-index:2147483647 !important; bottom:70px !important; right:45px !important;}
                .indexer_small { font-size: .90rem !important;}
                .indexer pre { background: #fff !important; color:#000 !important; padding:10px; !important; margin:0 !important; border: none !important; }
                .indexer { width:100% !important; height:100% !important; position: fixed !important; background: #edf1f3 !important; top:0 !important; left:0 !important; color:#000 !important; padding:25px !important; z-index:999999999 !important; margin:0; overflow:auto; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif !important; font-size:1rem !important; line-height: 1rem !important; }
                .indexer * { font-size:.75rem !important; }
                .indexer_section { background: #fff !important; margin:0 0 20px 0 !important; border:1px solid #dae0e5 !important; border-radius:5px !important; }
                .indexer_section_details { padding:10px !important; font-size:.90rem !important; background: #dae0e5; }
                .indexer .sql pre { border-top: 1px solid #edf1f3 !important; border-bottom: 1px solid #edf1f3 !important; }
                .indexer .left { float: left !important; }
                .indexer .right { float: right !important; }
                .indexer .clear { clear: both !important; }
                .indexer .padded { padding:10px !important; font-size: .90rem !important; color:#555 !important; }
                .indexer .hint { background: #a1ff8e !important; padding:2px 5px !important; border-radius: 5px !important; margin: 0 0 5px 0 !important; display: inline-block !important; font-weight: bold !important; }
                .indexer .error { background:#ff6586 !important; color:#fff !important; font-weight:bold !important; text-align:center !important; border:1px solid red !important; padding:10px !important; margin:10px 0 !important;}
            </style>
OUTOUT;

        $indexerColor = '#a1ff8e';
        $totalQueries = count($queries);
        $optimizationsCount = indexerGetOptimizedCount($queries);

        if (!$optimizationsCount || !config('indexer.watched_tables', [])) {
            $indexerColor = '#fff382';
        }

        // do we have any tables to watch
        if (!config('indexer.watched_tables', [])) {
            $output .= '<a href="#" class="indexer_query_info" style="background: ' . $indexerColor . ' !important;">INDEXER <span class="indexer_small">(No Tables Defined)</span></a>';
        } else {
            $output .= '<a href="#" class="indexer_query_info" style="background: ' . $indexerColor . ' !important;">INDEXER <span class="number"><span class="indexer_opt">' . $optimizationsCount . '</span>/<span class="indexer_total">' . $totalQueries . '</span></span></a>';
        }

        $output .= '<div class="indexer_alert" style="display: none;">New result(s) added from ajax request.</div>';

        $output .= '<div class="indexer" style="display: none;">';

        $output .= makeExplainResults($queries);

        $output .= '<div class="indexer_ajax_placeholder"></div>';

        if ($queries) {
            $skippedTables = array_unique(end($queries)['skippedTables']);

            if ($skippedTables) {
                $output .= '<div class="indexer_section">';
                $output .= '<div class="indexer_section_details">Following tables were skipped:</div>';
                $output .= '<div class="padded">' . implode(' | ', $skippedTables) . '</div>';
                $output .= '</div>';
            }
        }

        $output .= '<div style="margin:0 0 75px 0 !important;"></div>';
        $output .= '</div>';


        $output .= <<< OUTOUT
        <script>
            // toggle indexer results page
            document.querySelector(".indexer_query_info").addEventListener("click", function(e) {
                var indexer = document.querySelector(".indexer");
                e.preventDefault();
                
                indexer.style.display = indexer.style.display === "none" ? "block" : "none";
            });
        </script>
OUTOUT;

        if (config('indexer.check_ajax_requests', false)) {
            $ajaxRequestUrl = route('indexer_get_ajax_request_results');
            $ajaxPollingInterval = config('indexer.ajax_requests_polling_interval', 15000);

            $output .= <<< OUTOUT
            <script>            
                // see if we have new entries found in ajax requests
                document.addEventListener('DOMContentLoaded', function() {
                    var alreadyAdded = [];
                    
                    setInterval(function() {
                        indexerAjaxGet('$ajaxRequestUrl', function(response){
                            if (response) {
                                var total = parseInt(document.querySelector(".indexer_total").innerHTML, 10);
                                var optimized = parseInt(document.querySelector(".indexer_opt").innerHTML, 10);
                                
                                response = JSON.parse(response);
                                
                                if (!alreadyAdded.includes(response.key)) {
                                    alreadyAdded.push(response.key);
                                    
                                    if (response.counts.optimized > 0) {
                                        document.querySelector(".indexer_query_info").style.background = "#a1ff8e";
                                    }

                                    document.querySelector(".indexer_ajax_placeholder").innerHTML += response.content;
                                    document.querySelector(".indexer_total").innerHTML = (total + response.counts.total);
                                    document.querySelector(".indexer_opt").innerHTML = (optimized + response.counts.optimized);
                                    
                                    document.querySelector(".indexer_alert").style.display = "block";
                                    
                                    setTimeout(function() {
                                        document.querySelector(".indexer_alert").style.display = "none";
                                    }, 10000);
                                }
                            }
                        });                  
                    }, $ajaxPollingInterval);
                });   
                
                function indexerAjaxGet(url, callback) {
                    var xhr = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject('Microsoft.XMLHTTP');
                    
                    xhr.open('GET', url);
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState > 3 && xhr.status === 200) callback(xhr.responseText);
                    };
                    
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.send();
                    
                    return xhr;
                }            
                
            </script>
OUTOUT;

        }

        $output .= '<!--end_indexer_response-->';

        return $output;
    }
}

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

        if ($request->ajax()) {

            if ($queries && config('indexer.check_ajax_requests', false)) {
                $response->headers->set('indexer_ajax_response', json_encode($queries));
            }

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
                .indexer .sql { border-top: 1px solid #edf1f3 !important; border-bottom: 1px solid #edf1f3 !important; color:#666; !important; padding: 10px 15px !important; font-weight: bold !important; background: #f4f6f7 !important; }
                .indexer .left { float: left !important; }
                .indexer .right { float: right !important; }
                .indexer .clear { clear: both !important; }
                .indexer .padded { padding:10px !important; font-size: .90rem !important; color:#555 !important; }
                .indexer .hint { background: #a1ff8e !important; padding:2px 5px !important; border-radius: 5px !important; margin: 0 0 5px 0 !important; display: inline-block !important; font-weight: bold !important; }
                .indexer .error { background:#ff6586 !important; color:#fff !important; font-weight:bold !important; text-align:center !important; border:1px solid red !important; padding:10px !important; margin:10px 0 !important;}
                .indexer .indexer_table * { background:#fff !important;}
                .indexer .indexer_table { border-collapse: collapse !important; width: 98% !important; margin: 20px auto !important;}
                .indexer .indexer_table td, .indexer .indexer_table th { padding: 3px !important; font-weight: normal !important; text-align: center !important; border: 1px solid #dae0e5; word-wrap: break-word !important;}
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

        $output .= '<div class="indexer_alert" style="display: none;"></div>';

        $output .= '<div class="indexer" style="display: none;">';

        $output .= indexerMakeExplainResults($queries);

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
            $output .= <<< OUTOUT
            
            <script>
            // intercept ajax requests to see if Indexer detected any queries
            (function (XHR) {
                "use strict";
    
                var open = XHR.prototype.open;
                var send = XHR.prototype.send;
                
                var alreadyAdded = [];
    
                XHR.prototype.open = function (method, url, async, user, pass) {
                    this._url = url;
                    open.call(this, method, url, async, user, pass);
                };
    
                XHR.prototype.send = function (data) {
                    var self = this;
                    var oldOnReadyStateChange;
                    var url = this._url;
    
                    function onReadyStateChange() {
                        if (self.readyState === 4) {
                            var headers = parseResponseHeaders(this.getAllResponseHeaders()).indexer_ajax_response || null;
                            
                            if (headers) {                                
                                var output = '<div class="padded"><strong>Added from Ajax Request</strong></div>';                        
                                var count = 0;
                                var optimizedCount = 0;

                                var queries = JSON.parse(headers);

                                for(var x in queries) {
                                    if (queries.hasOwnProperty(x)) {
                                        count++;

                                        var total = parseInt(document.querySelector(".indexer_total").innerHTML, 10);
                                        var optimized = parseInt(document.querySelector(".indexer_opt").innerHTML, 10);

                                        if (!alreadyAdded.includes(x)) {
                                            alreadyAdded.push(x);

                                            var hasOptimized = queries[x]['explain_result']['key'] && queries[x]['explain_result']['key'].trim();
                                            var bgColor = hasOptimized ? '#91e27f' : '#dae0e5';

                                            output += '<div class="indexer_section">';
                                            output += '<div class="indexer_section_details" style="background: ' + bgColor + '">';
                                            output += "<div class='left'><strong>" + queries[x]['index_name'] + "</strong></div>";
                                            output += "<div class='right'><strong>" + queries[x]['time'] + "</strong></div>";
                                            output += "<div class='clear'></div>";
                                            output += '</div>';
                                            output += "<div class='padded'>";
                                            output += "File: <strong>" + queries[x]['file'] + "</strong><br>";
                                            output += "Line: <strong>" + queries[x]['line'] + "</strong>";
                                            output += '</div>';
                                            output += '<div class="sql">' + queries[x]['sql'] + '</div>';
                                            output += indexerTable(queries[x]['explain_result']);

                                            if (queries[x]['hints']) {
                                                output += "<div class='padded'>";

                                                queries[x]['hints'].forEach(function(item) {
                                                    output += "<span class='hint'>Hint</span> <strong>" + item + "</strong><br>";
                                                });

                                                output += '</div>';
                                            }

                                            output += '</div>';
                                            
                                            document.querySelector(".indexer_ajax_placeholder").innerHTML += output;
                                            document.querySelector(".indexer_total").innerHTML = (total + count);
                                            
                                            if (hasOptimized) {
												optimizedCount++;
                                                document.querySelector(".indexer_query_info").style.background = '#a1ff8e';
                                                document.querySelector(".indexer_opt").innerHTML = (optimized + optimizedCount);   
                                            }
                                            
                                            document.querySelector(".indexer_alert").innerHTML = ((total + count) - count) + " new result(s) added from ajax request.";
                                            document.querySelector(".indexer_alert").style.display = "block";

                                            setTimeout(function() {
                                                document.querySelector(".indexer_alert").style.display = "none";
                                            }, 10000);

                                        }
                                    }
                                }
                            }
                        }

                        if (oldOnReadyStateChange) {
                            oldOnReadyStateChange();
                        }
                    }
    
                    /* Set xhr.noIntercept to true to disable the interceptor for a particular call */
                    if (!this.noIntercept) {
                        if (this.addEventListener) {
                            this.addEventListener("readystatechange", onReadyStateChange, false);
                        } else {
                            oldOnReadyStateChange = this.onreadystatechange;
                            this.onreadystatechange = onReadyStateChange;
                        }
                    }
    
                    send.call(this, data);
                };
                
                function parseResponseHeaders(headerStr) {
                    return Object.fromEntries(
                        (headerStr || '').split('\\u000d\\u000a')
                            .map(line => line.split('\\u003a\\u0020'))
                            .filter(pair => pair[0] !== undefined && pair[1] !== undefined)
                    );
                }
                
                function indexerTable(obj) {
                    var html = '<table class="indexer_table">';
                    
                    html += '<tr>';
                    Object.keys(obj).forEach(function (item, index) {
                      html += '<th>' + item + '</th>';
                    });
                    html += '</tr>';
                    
                    html += '<tr>';
                    Object.values(obj).forEach(function (item, index) {
                      html += '<td>' + (item || '') + '</td>';
                    });
                    html += '</tr>';
                    
                    html += '</table>';
                    
                    return html;
                }                

                if (!String.prototype.trim) {
                    (function() {
                        var rtrim = /^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g;
                        String.prototype.trim = function() {
                            return this.replace(rtrim, '');
                        };
                    })();
                }
                
            })(XMLHttpRequest);
        </script>
OUTOUT;
        }

        $output .= '<!--end_indexer_response-->';

        return $output;
    }
}

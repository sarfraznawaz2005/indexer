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
        if (stripos($response->headers->get('Content-Type'), 'text/html') !== 0) {
            //return;
        }

        if (app()->runningInConsole()) {
            return;
        }

        if (!$request->acceptsHtml()) {
            return;
        }

        if ($request->ajax() || $request->expectsJson()) {

            if ($queries && config('indexer.check_ajax_requests', false)) {
                $response->headers->set('indexer_ajax_response', json_encode($queries));
            }

            return;
        }

        $content = $response->getContent();
        $outputContent = $this->getOutputContent($queries);
        $position = strripos($content, '</head>');

        if (false !== $position) {
            $content = substr($content, 0, $position) . $outputContent . substr($content, $position);
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
        $output = '';

        $fontSize = config('indexer.font_size', '12px');

        $output .= <<< OUTOUT
            <style>
                .indexer_query_info, .indexer_query_info:active .indexer_query_info:visited .indexer_query_info:hover { position:fixed !important; z-index:2147483647 !important; bottom:20px !important; right:45px !important; padding: 2px 10px 5px 10px !important; font-size:20px !important; border-radius:5px !important;color:#333 !important; text-decoration: none !important; }
                .indexer_query_info .number { font-weight: bold !important; font-size: 24px !important; }
                .indexer_alert { background: #a1ff8e !important; padding:2px 5px !important; border-radius: 5px !important; position:fixed !important; z-index:2147483647 !important; bottom:70px !important; right:45px !important; color:#000 !important}
                .indexer_small { font-size: 70% !important;}
                .indexer .indexer_nothing { text-align: center !important; position: absolute !important; top:150px !important; width: 96% !important; font-weight: bold !important; font-size: 34px !important; color:#c0c0c0 !important; }
                .indexer pre { background: #fff !important; color:#000 !important; padding:10px; !important; margin:0 !important; border: none !important; }
                .indexer { font-size:$fontSize !important; line-height: 150% !important; width:100% !important; height:100% !important; position: fixed !important; background: #edf1f3 !important; top:0 !important; left:0 !important; color:#000 !important; padding:25px !important; z-index:999999999 !important; margin:0; overflow:auto; font-family: arial, sans-serif !important; }
                .indexer * { font-size:$fontSize !important; }
                .indexer_section { background: #fff !important; margin:0 0 20px 0 !important; border:1px solid #dae0e5 !important; border-radius:5px !important; }
                .indexer_section_details { padding:10px !important; background: #dae0e5; }
                .indexer .sql { border: 1px solid #e8ebed !important; color:#666; !important; padding: 10px 10px !important; margin: 5px 13px !important; font-weight: bold !important;  }
                .indexer .sql_keyword { color:royalblue !important; text-transform: uppercase !important; }
                .indexer .left { float: left !important; }
                .indexer .right { float: right !important; }
                .indexer .clear { clear: both !important; }
                .indexer .padded { padding:10px !important; color:#555 !important; }
                .indexer .hint { background: #a1ff8e !important; padding:2px 5px !important; border-radius: 5px !important; margin: 0 0 5px 0 !important; display: inline-block !important; font-weight: bold !important; }
                .indexer .info { background: #dae0e5 !important; padding:2px 5px !important; border-radius: 5px !important; margin: 0 0 5px 0 !important; display: inline-block !important; color:#444 !important; }
                .indexer .error { background:#ff6586 !important; color:#fff !important; font-weight:bold !important; text-align:center !important; border:1px solid red !important; padding:10px !important; margin:10px 0 !important;}
                .indexer .indexer_table * { background:#f4f6f7 !important; color:#555; !important; }
                .indexer .indexer_table { border-collapse: collapse !important; width: 98% !important; margin: 10px auto !important;}
                .indexer .indexer_table td, .indexer .indexer_table th { padding: 3px !important; font-weight: normal !important; text-align: center !important; border: 1px solid #e8ebed; word-wrap: break-word !important;}
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

        if ($totalQueries) {
            $skippedTables = array_unique(end($queries)['skippedTables']);

            if ($skippedTables) {
                $output .= '<div class="indexer_section_details">Following tables were skipped:</div>';
                $output .= '<div class="padded">' . implode(' | ', $skippedTables) . '</div>';
                $output .= '</div>';
            }
        } else {
            $output .= '<div class="indexer_nothing">Nothing Yet :(</div>';
        }

        $output .= '</div>';

        $output .= <<< OUTOUT

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector(".indexer_query_info").addEventListener("click", function(e) {
        var indexer = document.querySelector(".indexer");
        e.preventDefault();
        
        indexer.style.display = indexer.style.display === "none" ? "block" : "none";    
    });
    
    indexerHighlight();
});

function indexerHighlight() {
    var sqlReg = /\b(AND|AS|ASC|BETWEEN|BY|CASE|CURRENT_DATE|CURRENT_TIME|DELETE|DESC|DISTINCT|EACH|ELSE|ELSEIF|FALSE|FOR|FROM|GROUP|HAVING|IF|IN|INSERT|INTERVAL|INTO|IS|JOIN|KEY|KEYS|LEFT|LIKE|LIMIT|MATCH|NOT|NULL|ON|OPTION|OR|ORDER|OUT|OUTER|REPLACE|RIGHT|SELECT|SET|TABLE|THEN|TO|TRUE|UPDATE|VALUES|WHEN|WHERE|CREATE|ALTER|ALL|DATABASE|GRANT|PRIVILEGES|IDENTIFIED|FLUSH|INNER|COUNT)(?=[^\w])/ig;

    document.querySelectorAll(".indexer .sql").forEach(function(item) {
        item.innerHTML = item.innerHTML.replace(sqlReg,'<span class="sql_keyword">$1</span>');
    });
}
</script>
OUTOUT;

        if (config('indexer.check_ajax_requests', false)) {
            $output .= <<< OUTOUT
            
<script>
(function(open, window) {

    var alreadyAdded = [];

    XMLHttpRequest.prototype.open = function(method, url, async, user, pass) {
        this.addEventListener('load', function() {
            var headers = parseResponseHeaders(this.getAllResponseHeaders()).indexer_ajax_response || null;

            if (headers) {
                var output = '<div class="info"><strong>Added from Ajax Request</strong></div>';

                var queries = JSON.parse(headers);

                for (var x in queries) {
                    if (queries.hasOwnProperty(x)) {
                        if (!alreadyAdded.includes(x)) {
                            alreadyAdded.push(x);

                            var hasOptimized = indexerOptimizedKey(queries[x]['explain_result']);
                            var bgColor = hasOptimized ? '#91e27f' : '#dae0e5';
                            var optimizedClass = hasOptimized ? 'optimized' : '';
                            
                            output += '<div class="indexer_section ' + optimizedClass + '">';
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

                            if (document.querySelector(".indexer .indexer_nothing")) {
                                document.querySelector(".indexer .indexer_nothing").style.display = "none";
                            }
                            
                            document.querySelector(".indexer .indexer_ajax_placeholder").innerHTML += output;
                            
                            var total = document.querySelectorAll(".indexer .indexer_section").length;
                            var optimizedTotal = document.querySelectorAll(".indexer .optimized").length;
                            
                            document.querySelector(".indexer_total").innerHTML = total;
                            document.querySelector(".indexer_opt").innerHTML = optimizedTotal;

                            if (hasOptimized) {
                                document.querySelector(".indexer_query_info").style.background = '#a1ff8e';
                            }
                            
                            window.indexerHighlight();
                            
                            document.querySelector(".indexer_alert").innerHTML = "New result(s) added from ajax request.";
                            document.querySelector(".indexer_alert").style.display = "block";

                            setTimeout(function() {
                                document.querySelector(".indexer_alert").style.display = "none";
                            }, 10000);
                            
                        }
                    }
                }
            }
        }, false);

        open.call(this, method, url, async, user, pass);
    };
    
    /* Decides if query is optimized */
    function indexerOptimizedKey(explain_result) {
        if (typeof indexerOptimizedKeyCustom !== 'undefined' && typeof indexerOptimizedKeyCustom === 'function') {
            return indexerOptimizedKeyCustom(explain_result);
        }
        
        return explain_result['key'].trim();
    }

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

        Object.keys(obj).forEach(function(item, index) {
            html += '<th>' + item + '</th>';
        });
        
        html += '</tr>';
        html += '<tr>';

        Object.values(obj).forEach(function(item, index) {
            html += '<td>' + (item || '') + '</td>';
        });

        html += '</tr>';
        html += '</table>';

        return html;
    }

})(XMLHttpRequest.prototype.open, window);
</script>

OUTOUT;
        }

        $output = "\n<!--indexer_start-->\n" . $this->compress($output) . "\n<!--indexer_end-->\n\n";

        return $output;
    }

    /**
     * Compress indexer response
     *
     * @param $html
     * @return string
     */
    private function compress($html): string
    {
        ini_set('pcre.recursion_limit', '16777');
        // enable GZip, too!
        ini_set('zlib.output_compression', 'On');

        $regEx = '%# Collapse whitespace everywhere but in blacklisted elements.
        (?>             # Match all whitespans other than single space.
          [^\S ]\s*     # Either one [\t\r\n\f\v] and zero or more ws,
        | \s{2,}        # or two or more consecutive-any-whitespace.
        ) # Note: The remaining regex consumes no text at all...
        (?=             # Ensure we are not in a blacklist tag.
          [^<]*+        # Either zero or more non-"<" {normal*}
          (?:           # Begin {(special normal*)*} construct
            <           # or a < starting a non-blacklist tag.
            (?!/?(?:textarea|pre)\b)
            [^<]*+      # more non-"<" {normal*}
          )*+           # Finish "unrolling-the-loop"
          (?:           # Begin alternation group.
            <           # Either a blacklist start tag.
            (?>textarea|pre)\b
          | \z          # or end of file.
          )             # End alternation group.
        )  # If we made it here, we are not in a blacklist tag.
        %Six';

        $compressed = preg_replace($regEx, ' ', $html);

        if ($compressed !== null) {
            $html = $compressed;
        }

        return trim($html);
    }
}

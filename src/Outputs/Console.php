<?php

namespace Sarfraznawaz2005\QueryWatch\Outputs;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class Console implements Output
{
    public function boot()
    {
        //
    }

    public function output(Collection $detectedQueries, Response $response)
    {
        if ($response->isRedirection() || stripos($response->headers->get('Content-Type'), 'text/html') !== 0) {
            return;
        }

        $content = $response->getContent();

        $outputContent = $this->getOutputContent($detectedQueries);

        $pos = strripos($content, '</body>');

        if (false !== $pos) {
            $content = substr($content, 0, $pos) . $outputContent . substr($content, $pos);
        } else {
            $content = $content . $outputContent;
        }

        // Update the new content and reset the content length
        $response->setContent($content);

        $response->headers->remove('Content-Length');
    }

    protected function getOutputContent(Collection $detectedQueries)
    {
        $output = '<script type="text/javascript">';
        $output .= "console.warn('Found the following queries in this request:\\n\\n";

        foreach ($detectedQueries as $detectedQuery) {
            $output .= $detectedQuery['sql'] . "\n";
        }

        $output .= "')";
        $output .= '</script>';

        return $output;
    }
}

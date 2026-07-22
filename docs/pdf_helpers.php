<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function markdownToHtml(string $markdown): string
{
    $html = $markdown;
    $html = preg_replace('/```([\s\S]*?)```/m', '<pre><code>$1</code></pre>', $html);
    $html = preg_replace('/^###### (.+)$/m', '<h6>$1</h6>', $html);
    $html = preg_replace('/^##### (.+)$/m', '<h5>$1</h5>', $html);
    $html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
    $html = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<div class="screenshot"><img src="$2" alt="$1"><p class="caption">$1</p></div>', $html);
    $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html);
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
    $html = preg_replace('/^---$/m', '<hr>', $html);
    $html = preg_replace('/^> (.+)$/m', '<blockquote>$1</blockquote>', $html);

    $html = preg_replace_callback('/(\|.+\|\n\|[-| :]+\|\n(?:\|.+\|\n?)+)/', function ($matches) {
        $lines = array_filter(explode("\n", trim($matches[0])));
        $table = '<table>';
        foreach ($lines as $i => $line) {
            if (preg_match('/^\|[-| :]+\|$/', trim($line))) {
                continue;
            }
            $cells = array_map('trim', array_filter(explode('|', trim($line, '|'))));
            $tag = ($i === 0) ? 'th' : 'td';
            $table .= '<tr>';
            foreach ($cells as $cell) {
                $table .= "<{$tag}>" . htmlspecialchars($cell) . "</{$tag}>";
            }
            $table .= '</tr>';
        }
        return $table . '</table>';
    }, $html);

    $html = preg_replace_callback('/(?:^- .+\n?)+/', function ($matches) {
        $items = preg_replace('/^- (.+)$/m', '<li>$1</li>', trim($matches[0]));
        return '<ul>' . $items . '</ul>';
    }, $html);

    $html = preg_replace_callback('/(?:^\d+\. .+\n?)+/m', function ($matches) {
        $items = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', trim($matches[0]));
        return '<ol>' . $items . '</ol>';
    }, $html);

    $lines = explode("\n", $html);
    $result = [];
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '') {
            $result[] = '';
            continue;
        }
        if (preg_match('/^<(h[1-6]|ul|ol|li|table|tr|td|th|pre|blockquote|div|hr|img)/', $trim)) {
            $result[] = $line;
        } else {
            $result[] = '<p>' . $line . '</p>';
        }
    }

    return implode("\n", $result);
}

function embedImages(string $html, string $baseDir): string
{
    return preg_replace_callback('/src="([^"]+)"/', function ($matches) use ($baseDir) {
        $relative = $matches[1];
        if (preg_match('/^https?:\/\//', $relative)) {
            return $matches[0];
        }
        $path = realpath($baseDir . '/' . $relative);
        if (!$path || !file_exists($path)) {
            return 'src="" alt="Screenshot tidak tersedia"';
        }
        $mime = mime_content_type($path);
        $data = base64_encode(file_get_contents($path));
        return 'src="data:' . $mime . ';base64,' . $data . '"';
    }, $html);
}

function renderPdf(string $fullHtml, string $outputPdf): void
{
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($fullHtml);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    file_put_contents($outputPdf, $dompdf->output());
}

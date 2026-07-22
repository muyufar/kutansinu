<?php
/**
 * Generator PDF Panduan User Bus
 * Usage: php docs/generate_bus_pdf.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$docsDir = __DIR__;
$markdownFile = $docsDir . '/panduan_user_bus.md';
$outputPdf = $docsDir . '/panduan_user_bus.pdf';

if (!file_exists($markdownFile)) {
    fwrite(STDERR, "File tidak ditemukan: {$markdownFile}\n");
    exit(1);
}

$markdown = file_get_contents($markdownFile);

function markdownToHtml(string $markdown): string
{
    $html = $markdown;

    // Code blocks
    $html = preg_replace('/```([\s\S]*?)```/m', '<pre><code>$1</code></pre>', $html);

    // Headings
    $html = preg_replace('/^###### (.+)$/m', '<h6>$1</h6>', $html);
    $html = preg_replace('/^##### (.+)$/m', '<h5>$1</h5>', $html);
    $html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

    // Images ![alt](path)
    $html = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<div class="screenshot"><img src="$2" alt="$1"><p class="caption">$1</p></div>', $html);

    // Links
    $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html);

    // Bold & inline code
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

    // Horizontal rule
    $html = preg_replace('/^---$/m', '<hr>', $html);

    // Blockquotes
    $html = preg_replace('/^> (.+)$/m', '<blockquote>$1</blockquote>', $html);

    // Tables
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

    // Unordered lists
    $html = preg_replace_callback('/(?:^- .+\n?)+/', function ($matches) {
        $items = preg_replace('/^- (.+)$/m', '<li>$1</li>', trim($matches[0]));
        return '<ul>' . $items . '</ul>';
    }, $html);

    // Ordered lists
    $html = preg_replace_callback('/(?:^\d+\. .+\n?)+/m', function ($matches) {
        $items = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', trim($matches[0]));
        return '<ol>' . $items . '</ol>';
    }, $html);

    // Paragraphs
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

$bodyHtml = markdownToHtml($markdown);
$bodyHtml = embedImages($bodyHtml, $docsDir);

$css = <<<'CSS'
@page { margin: 40px 36px; }
body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 11px;
    line-height: 1.55;
    color: #222;
}
h1 {
    color: #21922a;
    font-size: 22px;
    border-bottom: 3px solid #21922a;
    padding-bottom: 8px;
    margin-top: 0;
}
h2 {
    color: #1a5276;
    font-size: 16px;
    margin-top: 24px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 4px;
    page-break-after: avoid;
}
h3 { color: #34495e; font-size: 13px; margin-top: 16px; }
p { margin: 6px 0 10px; }
table {
    width: 100%;
    border-collapse: collapse;
    margin: 12px 0 16px;
    font-size: 10px;
}
th, td {
    border: 1px solid #ccc;
    padding: 6px 8px;
    text-align: left;
    vertical-align: top;
}
th { background: #f0f7f0; font-weight: bold; }
code, pre {
    background: #f4f4f4;
    font-family: DejaVu Sans Mono, monospace;
    font-size: 9px;
}
pre {
    padding: 10px;
    border-radius: 4px;
    white-space: pre-wrap;
}
blockquote {
    border-left: 4px solid #21922a;
    margin: 10px 0;
    padding: 6px 12px;
    background: #f9fff9;
    color: #333;
}
ul, ol { margin: 6px 0 12px 18px; }
li { margin-bottom: 4px; }
.screenshot {
    text-align: center;
    margin: 14px 0 18px;
    page-break-inside: avoid;
}
.screenshot img {
    max-width: 100%;
    border: 1px solid #ddd;
    border-radius: 6px;
}
.caption {
    font-size: 9px;
    color: #666;
    font-style: italic;
    margin-top: 4px;
}
.cover {
    text-align: center;
    padding: 80px 20px 40px;
    page-break-after: always;
}
.cover h1 { border: none; font-size: 28px; }
.cover .subtitle { font-size: 14px; color: #555; margin-top: 12px; }
.cover .meta { margin-top: 40px; font-size: 11px; color: #777; }
hr { border: none; border-top: 1px solid #ddd; margin: 20px 0; }
CSS;

$fullHtml = '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>'
    . '<div class="cover">'
    . '<h1>Panduan Pengguna<br>Sistem Pemesanan Bus</h1>'
    . '<p class="subtitle">NUGO INTL — Tutorial Lengkap untuk User Bus</p>'
    . '<p class="meta">Versi 1.0 | ' . date('F Y') . '</p>'
    . '</div>'
    . $bodyHtml
    . '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($fullHtml);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
file_put_contents($outputPdf, $dompdf->output());

echo "PDF berhasil dibuat: {$outputPdf}\n";

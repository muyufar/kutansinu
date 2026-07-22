<?php
/**
 * Generator PDF Panduan Penginputan Keuangan
 * Usage: php docs/generate_keuangan_pdf.php
 */

require_once __DIR__ . '/pdf_helpers.php';

$docsDir = __DIR__;
$markdownFile = $docsDir . '/panduan_penginputan_keuangan.md';
$outputPdf = $docsDir . '/panduan_penginputan_keuangan.pdf';

if (!file_exists($markdownFile)) {
    fwrite(STDERR, "File tidak ditemukan: {$markdownFile}\n");
    exit(1);
}

$markdown = file_get_contents($markdownFile);
$bodyHtml = embedImages(markdownToHtml($markdown), $docsDir);

$css = <<<'CSS'
@page { margin: 40px 36px; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; line-height: 1.55; color: #222; }
h1 { color: #21922a; font-size: 22px; border-bottom: 3px solid #21922a; padding-bottom: 8px; margin-top: 0; }
h2 { color: #1a5276; font-size: 16px; margin-top: 24px; border-bottom: 1px solid #ddd; padding-bottom: 4px; page-break-after: avoid; }
h3 { color: #34495e; font-size: 13px; margin-top: 16px; }
p { margin: 6px 0 10px; }
table { width: 100%; border-collapse: collapse; margin: 12px 0 16px; font-size: 10px; }
th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
th { background: #f0f7f0; font-weight: bold; }
code, pre { background: #f4f4f4; font-family: DejaVu Sans Mono, monospace; font-size: 9px; }
pre { padding: 10px; border-radius: 4px; white-space: pre-wrap; }
blockquote { border-left: 4px solid #21922a; margin: 10px 0; padding: 6px 12px; background: #f9fff9; }
ul, ol { margin: 6px 0 12px 18px; }
li { margin-bottom: 4px; }
.screenshot { text-align: center; margin: 14px 0 18px; page-break-inside: avoid; }
.screenshot img { max-width: 100%; border: 1px solid #ddd; border-radius: 6px; }
.caption { font-size: 9px; color: #666; font-style: italic; margin-top: 4px; }
.cover { text-align: center; padding: 80px 20px 40px; page-break-after: always; }
.cover h1 { border: none; font-size: 26px; }
.cover .subtitle { font-size: 14px; color: #555; margin-top: 12px; }
.cover .meta { margin-top: 40px; font-size: 11px; color: #777; }
hr { border: none; border-top: 1px solid #ddd; margin: 20px 0; }
CSS;

$fullHtml = '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>'
    . '<div class="cover">'
    . '<h1>Panduan Penginputan Keuangan<br>&amp; Pelaporan SiKeu</h1>'
    . '<p class="subtitle">Tutorial Lengkap Input Transaksi dan Laporan Keuangan</p>'
    . '<p class="meta">https://keuangan.numartmagelang.com | Versi 1.0 | ' . date('F Y') . '</p>'
    . '</div>'
    . $bodyHtml
    . '</body></html>';

renderPdf($fullHtml, $outputPdf);
echo "PDF berhasil dibuat: {$outputPdf}\n";

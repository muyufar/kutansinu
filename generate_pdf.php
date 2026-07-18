<?php
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Parsedown;

// Function to convert markdown to HTML
function markdownToHtml($markdown)
{
    $parsedown = new Parsedown();
    return $parsedown->text($markdown);
}

// Function to generate PDF
function generatePdf($html, $filename)
{
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Save PDF to file
    file_put_contents($filename, $dompdf->output());
}

// CSS styles for the PDF
$css = '
<style>
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        margin: 40px;
    }
    h1 {
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }
    h2 {
        color: #34495e;
        margin-top: 30px;
    }
    h3 {
        color: #7f8c8d;
    }
    code {
        background-color: #f8f9fa;
        padding: 2px 4px;
        border-radius: 4px;
        font-family: monospace;
    }
    pre {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        overflow-x: auto;
    }
    table {
        border-collapse: collapse;
        width: 100%;
        margin: 20px 0;
    }
    th, td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    th {
        background-color: #f8f9fa;
    }
    .toc {
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 5px;
        margin: 20px 0;
    }
    .footer {
        text-align: center;
        margin-top: 50px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
        font-size: 12px;
        color: #7f8c8d;
    }
</style>
';

// Read markdown files
$userGuide = file_get_contents('docs/user_guide.md');
$technicalDoc = file_get_contents('docs/technical_documentation.md');

// Convert markdown to HTML
$userGuideHtml = markdownToHtml($userGuide);
$technicalDocHtml = markdownToHtml($technicalDoc);

// Combine HTML with CSS
$userGuideFullHtml = $css . $userGuideHtml;
$technicalDocFullHtml = $css . $technicalDocHtml;

// Generate PDFs
generatePdf($userGuideFullHtml, 'docs/user_guide.pdf');
generatePdf($technicalDocFullHtml, 'docs/technical_documentation.pdf');

echo "PDF documentation has been generated successfully!\n";

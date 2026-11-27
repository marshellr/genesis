<?php
declare(strict_types=1);
require __DIR__ . '/../../dma/backend/inc/safe-html.php';

$document = dma_parse_html('<!doctype html><html><body><table><tr><td>Synthetic &amp; safe</td></tr></table></body></html>');
if (!$document || trim($document->getElementsByTagName('td')->item(0)->textContent) !== 'Synthetic & safe') {
    throw new RuntimeException('Normal HTML parsing failed');
}
foreach ([
    '',
    "<p>synthetic\0</p>",
    str_repeat('x', DMA_HTML_MAX_BYTES + 1),
    '<!DOCTYPE html [<!ENTITY synthetic "value">]><p>synthetic</p>',
    '<!DOCTYPE html SYSTEM "https://example.invalid/synthetic.dtd"><p>synthetic</p>',
    '<!ENTITY synthetic SYSTEM "file:///synthetic-never-read"><p>synthetic</p>',
] as $input) {
    if (dma_parse_html($input) !== null) {
        throw new RuntimeException('Unsafe or oversized parser input accepted');
    }
}
if (libxml_use_internal_errors() !== false) {
    throw new RuntimeException('Parser error state leaked');
}
echo "DMA bounded HTML parser tests passed\n";

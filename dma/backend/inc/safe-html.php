<?php
declare(strict_types=1);

const DMA_HTML_MAX_BYTES = 2 * 1024 * 1024;

/** Bound third-party HTML and reject DTD/entity input before invoking libxml. */
function dma_parse_html(string $html): ?DOMDocument
{
    if ($html === '' || strlen($html) > DMA_HTML_MAX_BYTES || str_contains($html, "\0")
        || preg_match('/<!\s*ENTITY\b|<!\s*DOCTYPE\b(?!\s+html\s*>)/i', $html)) {
        return null;
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument();
        return $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT)
            ? $document : null;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

/** Third-party pages are HTTPS-only; oversized responses fail into existing fallbacks. */
function dma_fetch_html(string $url, array $headers): ?string
{
    $body = '';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > DMA_HTML_MAX_BYTES) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    try {
        $ok = curl_exec($curl);
        return $ok && curl_getinfo($curl, CURLINFO_RESPONSE_CODE) === 200 && $body !== '' ? $body : null;
    } finally {
        curl_close($curl);
    }
}

<?php
declare(strict_types=1);

return [
    'brand' => getenv('PORTFOLIO_BRAND') ?: 'shellr',
    'role' => getenv('PORTFOLIO_ROLE') ?: 'DevOps / System Engineer',
    'location' => getenv('PORTFOLIO_LOCATION') ?: 'Germany',
    'contact_email' => getenv('PORTFOLIO_CONTACT_EMAIL') ?: 'marlin.scheler@shellr.net',
    'github_url' => getenv('PORTFOLIO_GITHUB_URL') ?: '',
    'docs_url' => getenv('PORTFOLIO_DOCS_URL') ?: 'https://docs.shellr.net',
    'dma_url' => getenv('PORTFOLIO_DMA_URL') ?: 'https://dma.shellr.net',
    'status_url' => getenv('PORTFOLIO_STATUS_URL') ?: 'https://status.shellr.net',
    'grafana_url' => getenv('PORTFOLIO_GRAFANA_URL') ?: 'https://grafana.shellr.net',
];

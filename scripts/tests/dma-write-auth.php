<?php
declare(strict_types=1);

require __DIR__ . '/../../dma/backend/inc/write-auth.php';

$file = tempnam(sys_get_temp_dir(), 'dma-auth-test-');
if ($file === false) {
    throw new RuntimeException('No temporary test file');
}
try {
    file_put_contents($file, password_hash('synthetic-test-password', PASSWORD_DEFAULT));
    chmod($file, 0600);
    $settings = ['DMA_WRITE_USERNAME' => 'test-operator', 'DMA_WRITE_PASSWORD_HASH_FILE' => $file];
    $cases = [
        [[], false],
        [['REMOTE_USER' => 'test-operator', 'HTTP_X_FORWARDED_USER' => 'test-operator'], false],
        [['PHP_AUTH_USER' => 'test-operator', 'PHP_AUTH_PW' => 'incorrect'], false],
        [['PHP_AUTH_USER' => 'other-user', 'PHP_AUTH_PW' => 'synthetic-test-password'], false],
        [['PHP_AUTH_USER' => 'test-operator', 'PHP_AUTH_PW' => 'synthetic-test-password'], true],
        [['REQUEST_METHOD' => 'GET', 'PHP_AUTH_USER' => 'test-operator', 'PHP_AUTH_PW' => 'synthetic-test-password'], false],
        [['REQUEST_METHOD' => 'HEAD', 'PHP_AUTH_USER' => 'test-operator', 'PHP_AUTH_PW' => 'synthetic-test-password'], false],
    ];
    foreach ($cases as [$server, $expected]) {
        if (dma_write_authorized(array_replace(['REQUEST_METHOD' => 'POST'], $server), $settings) !== $expected) {
            throw new RuntimeException('Authorization regression');
        }
    }
    if (dma_write_authorized(array_replace(['REQUEST_METHOD' => 'POST'], $cases[4][0]), []) !== false) {
        throw new RuntimeException('Writes must stay disabled without operator credentials');
    }
    echo "DMA authorization tests passed\n";
} finally {
    unlink($file);
}

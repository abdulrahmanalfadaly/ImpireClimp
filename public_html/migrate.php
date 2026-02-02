<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/migration.php';

$key = $_GET['key'] ?? '';

if ($key === '' || !hash_equals(ADMIN_KEY, $key)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$logs = [];

try {
    run_migrations(get_pdo(), $logs);
} catch (Throwable $e) {
    $logs[] = '[FAIL] ' . $e->getMessage();
    http_response_code(500);
}

foreach ($logs as $line) {
    echo $line . PHP_EOL;
}


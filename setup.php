<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';

$sql = file_get_contents(__DIR__ . '/sql/schema.sql');
if ($sql === false) {
    exit('Unable to read schema file.');
}

try {
    db()->exec($sql);
    echo "Database schema created successfully.";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Setup failed: ' . $e->getMessage();
}

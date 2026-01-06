<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/gabo/portfolio/projects/06-sasp-php/php_errors.log');

echo "Testing PHP execution...\n";

try {
    require __DIR__ . '/config.php';
    echo "Config loaded OK\n";

    require __DIR__ . '/vendor/autoload.php';
    echo "Autoloader loaded OK\n";

    $app = new \Sasp\Web\Application();
    echo "Application instantiated OK\n";

    $app->run();
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

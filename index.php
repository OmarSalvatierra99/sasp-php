<?php

declare(strict_types=1);

/**
 * SASP — Entry Point
 * Front controller for the web application
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display to browser
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');

try {
    require __DIR__ . '/config.php';
    require __DIR__ . '/vendor/autoload.php';

    $app = new \Sasp\Web\Application();
    $app->run();
} catch (\Throwable $e) {
    error_log("Fatal error in index.php: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo "Internal Server Error";
}

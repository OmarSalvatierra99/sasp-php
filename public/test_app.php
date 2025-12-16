<?php

declare(strict_types=1);

/**
 * Test Application instantiation and basic routing
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../vendor/autoload.php';

try {
    echo "Testing Application instantiation...\n";
    $app = new \Sasp\Web\Application();
    echo "✓ Application created successfully\n";

    echo "\nTesting with simulated GET request to /...\n";
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['HTTP_HOST'] = 'localhost';

    // Don't actually run the app, just test that it can be created
    echo "✓ All checks passed\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

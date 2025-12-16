<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

echo "<pre>";
echo "=== SASP Web Test ===\n\n";

try {
    echo "1. Loading autoloader...\n";
    require __DIR__ . '/../vendor/autoload.php';
    echo "   ✓ Autoloader loaded\n\n";

    echo "2. Creating Application...\n";
    $app = new \Sasp\Web\Application();
    echo "   ✓ Application created\n\n";

    echo "3. Testing Request with REQUEST_METHOD...\n";
    $request = \Sasp\Web\Request::fromGlobals($_SESSION ?? []);
    echo "   Method: " . $request->method() . "\n";
    echo "   Path: " . $request->path() . "\n";
    echo "   ✓ Request created successfully\n\n";

    echo "4. All tests passed!\n";
    echo "   The fix is working correctly.\n";

} catch (\Throwable $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";

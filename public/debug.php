<?php

declare(strict_types=1);

use Sasp\Web\Request;

$logFile = __DIR__ . '/../debug.log';
$log = date('Y-m-d H:i:s') . " - Starting debug\n";

try {
    $log .= "Loading autoloader...\n";

    require __DIR__ . '/../vendor/autoload.php';
    $log .= "Autoloader OK\n";

    $request = Request::fromGlobals($_SESSION ?? []);
    $log .= "REQUEST_METHOD: " . $request->method() . "\n";
    $log .= "REQUEST_URI: " . $request->uri() . "\n";
    $log .= "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'NOT SET') . "\n";

    $log .= "Creating Application...\n";
    $app = new \Sasp\Web\Application();
    $log .= "Application OK\n";

    $log .= "SUCCESS - All components loaded\n";
    file_put_contents($logFile, $log, FILE_APPEND);

    echo "OK - Check debug.log for details";

} catch (\Throwable $e) {
    $log .= "ERROR: " . $e->getMessage() . "\n";
    $log .= "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    $log .= "Trace: " . $e->getTraceAsString() . "\n";
    file_put_contents($logFile, $log, FILE_APPEND);

    echo "ERROR - Check debug.log for details";
    http_response_code(500);
}

<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_debug.log');

echo "=== Simulating Application construction ===\n";

// Simulate what happens in Application.php
$projectRoot = dirname('/home/gabo/portfolio/projects/06-sasp-php/src/Web', 2);
echo "Project root (from Application): " . $projectRoot . "\n";

$defaultDbPath = $projectRoot . '/scil.db';
echo "Default DB path: " . $defaultDbPath . "\n";

$configuredPath = (string)($_SERVER['SCIL_DB'] ?? getenv('SCIL_DB') ?: $defaultDbPath);
echo "Configured path: " . $configuredPath . "\n";

$dbPath = str_starts_with($configuredPath, DIRECTORY_SEPARATOR)
    ? $configuredPath
    : $projectRoot . '/' . ltrim($configuredPath, '/');
echo "DB path to pass to DatabaseManager: " . $dbPath . "\n";

// Now simulate what DatabaseManager does
echo "\n=== Simulating DatabaseManager::resolveDbPath ===\n";

$envPath = getenv('SCIL_DB');
if ($envPath !== false && $envPath !== '') {
    $dbPath = $envPath;
    echo "Using env path: " . $dbPath . "\n";
}

$isAbsolute = str_starts_with($dbPath, DIRECTORY_SEPARATOR)
    || (bool)preg_match('/^[A-Za-z]:[\\\\\\/]/', $dbPath);
echo "Is absolute: " . ($isAbsolute ? 'yes' : 'no') . "\n";

$projectRootFromDB = dirname('/home/gabo/portfolio/projects/06-sasp-php/src/Core', 2);
echo "Project root (from DatabaseManager): " . $projectRootFromDB . "\n";

$path = $isAbsolute ? $dbPath : $projectRootFromDB . DIRECTORY_SEPARATOR . $dbPath;
echo "Final path before realpath: " . $path . "\n";

$resolvedPath = realpath($path) ?: $path;
echo "Resolved path after realpath: " . $resolvedPath . "\n";

echo "\n=== File existence ===\n";
echo "Does file exist? " . (file_exists($path) ? 'yes' : 'no') . "\n";
echo "Is readable? " . (is_readable($path) ? 'yes' : 'no') . "\n";
echo "Is writable? " . (is_writable($path) ? 'yes' : 'no') . "\n";

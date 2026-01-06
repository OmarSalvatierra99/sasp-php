<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "Current directory: " . getcwd() . "\n";
echo "__FILE__: " . __FILE__ . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "dirname(__DIR__, 2): " . dirname(__DIR__, 2) . "\n";

echo "\nFrom Application.php path:\n";
$appDir = __DIR__ . '/src/Web';
echo "App __DIR__: " . $appDir . "\n";
echo "dirname(\$appDir, 1): " . dirname($appDir, 1) . "\n";
echo "dirname(\$appDir, 2): " . dirname($appDir, 2) . "\n";

echo "\nFrom DatabaseManager.php path:\n";
$dbDir = __DIR__ . '/src/Core';
echo "DB __DIR__: " . $dbDir . "\n";
echo "dirname(\$dbDir, 1): " . dirname($dbDir, 1) . "\n";
echo "dirname(\$dbDir, 2): " . dirname($dbDir, 2) . "\n";

echo "\nSCIL_DB environment:\n";
echo "\$_SERVER['SCIL_DB']: " . ($_SERVER['SCIL_DB'] ?? 'not set') . "\n";
echo "getenv('SCIL_DB'): " . (getenv('SCIL_DB') ?: 'not set') . "\n";

$defaultDbPath = dirname(__DIR__, 2) . '/scil.db';
echo "\nDefault DB path would be: " . $defaultDbPath . "\n";
echo "realpath of scil.db: " . (realpath(__DIR__ . '/scil.db') ?: 'not found') . "\n";

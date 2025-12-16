<?php

declare(strict_types=1);

/**
 * SASP — Public Entry Point
 * Front controller for the web application
 */

require __DIR__ . '/../vendor/autoload.php';

$app = new \Sasp\Web\Application();
$app->run();

<?php

declare(strict_types=1);

/**
 * Quick test to verify REQUEST_METHOD fix works
 */

require __DIR__ . '/vendor/autoload.php';

use Sasp\Web\Request;

echo "=== Testing REQUEST_METHOD handling ===\n\n";

// Test 1: Missing REQUEST_METHOD (CLI simulation)
echo "Test 1: Missing REQUEST_METHOD\n";
$request1 = new Request([], [], [], [], []);
echo "Result: " . $request1->method() . " (expected: GET)\n";
echo $request1->method() === 'GET' ? "✓ PASS\n\n" : "✗ FAIL\n\n";

// Test 2: POST method
echo "Test 2: POST method\n";
$request2 = new Request([], [], [], ['REQUEST_METHOD' => 'POST'], []);
echo "Result: " . $request2->method() . " (expected: POST)\n";
echo $request2->method() === 'POST' ? "✓ PASS\n\n" : "✗ FAIL\n\n";

// Test 3: Lowercase normalization
echo "Test 3: Lowercase normalization\n";
$request3 = new Request([], [], [], ['REQUEST_METHOD' => 'put'], []);
echo "Result: " . $request3->method() . " (expected: PUT)\n";
echo $request3->method() === 'PUT' ? "✓ PASS\n\n" : "✗ FAIL\n\n";

echo "=== All tests completed ===\n";

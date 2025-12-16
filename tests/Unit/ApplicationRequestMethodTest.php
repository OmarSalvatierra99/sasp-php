<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sasp\Web\Request;

/**
 * Test that Request safely handles missing REQUEST_METHOD
 * (e.g., in CLI context or edge cases)
 */
class ApplicationRequestMethodTest extends TestCase
{
    public function testRequestMethodDefaultsToGetWhenMissing(): void
    {
        $originalServer = $_SERVER ?? [];

        try {
            $_SERVER = [];
            $request = Request::fromGlobals();

            $this->assertSame('GET', $request->method());
        } finally {
            $_SERVER = $originalServer;
        }
    }

    public function testRequestMethodNormalizesToUppercase(): void
    {
        $request = new Request([], [], [], ['REQUEST_METHOD' => 'post'], []);

        $this->assertSame('POST', $request->method());
    }
}

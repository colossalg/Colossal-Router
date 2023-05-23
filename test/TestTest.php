<?php

declare(strict_types=1);

namespace Colossal\Routing;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Colossal\Routing\Test
 */
class TestTest extends TestCase
{
    public function setUp(): void
    {
        $this->test = new Test();
    }

    public function testGetString(): void
    {
        $this->assertEquals("Hello World!", $this->test->getString());
    }

    private Test $test;
}

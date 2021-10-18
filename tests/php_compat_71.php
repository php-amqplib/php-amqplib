<?php

namespace PhpAmqpLib\Tests;

use PHPUnit\Framework\TestCase;

class TestCaseCompat extends TestCase
{
    /**
     * This method should be used instead of default phpunit's setUp for compatibility with several phpunit versions
     */
    protected function setUpCompat()
    {
        parent::setUp();
    }

    /**
     * This method should be used instead of default phpunit's tearDown for compatibility with several phpunit versions
     */
    protected function tearDownCompat()
    {
        parent::tearDown();
    }

    protected function setUp(): void {
        static::setUpCompat();
    }

    protected function tearDown(): void {
        static::tearDownCompat();
    }
}

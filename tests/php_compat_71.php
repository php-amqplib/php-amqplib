<?php

/**
 * These two files are needed to support php < 7.1.
 * In modern versions of phpunit (after php 7.1 was released), signature of phpunit's setUp() and tearDown() methods changed.
 * Until PhpAmqpLib supports php <7.1, these files are needed to make tests work on old php versions
 */

namespace PhpAmqpLib\Tests;

class TestCaseCompat extends \PHPUnit\Framework\TestCase
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

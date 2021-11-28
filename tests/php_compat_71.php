<?php

namespace PhpAmqpLib\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Version;

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
        $this->setUpCompat();
    }

    protected function tearDown(): void {
        $this->tearDownCompat();
    }

    public static function assertPattern(string $pattern, string $string, string $message = ''): void
    {
        $series = Version::series();
        if (version_compare($series, '9.5') >= 0) {
            self::assertMatchesRegularExpression($pattern, $string, $message);
        } else {
            self::assertRegExp($pattern, $string, $message);
        }
    }
}

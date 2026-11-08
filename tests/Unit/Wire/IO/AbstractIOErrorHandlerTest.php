<?php

namespace PhpAmqpLib\Tests\Unit\Wire\IO;

use PhpAmqpLib\Wire\IO\AbstractIO;
use PHPUnit\Framework\TestCase;

/**
 * The error handler around socket calls turns warnings into exceptions. A deprecation raised
 * meanwhile, often by other code in the process (a signal handler, a coroutine), must not
 * become one.
 *
 * @group connection
 * @see https://github.com/php-amqplib/php-amqplib/issues/1243
 * @covers \PhpAmqpLib\Wire\IO\AbstractIO::setErrorHandler()
 */
class AbstractIOErrorHandlerTest extends TestCase
{
    /**
     * @test
     */
    public function deprecation_goes_to_the_previous_handler()
    {
        $seen = [];
        set_error_handler(function ($errno, $errstr) use (&$seen) {
            $seen[] = $errstr;

            return true;
        });
        try {
            $this->io()->guard(function () {
                trigger_error('old API', E_USER_DEPRECATED);
            });
        } finally {
            restore_error_handler();
        }

        $this->assertSame(['old API'], $seen);
    }

    /**
     * A handler that returns nothing (Laravel's does) has handled the error, as when PHP calls it
     * directly: PHP's standard handler must not report it a second time.
     *
     * @test
     */
    public function deprecation_handled_by_a_void_handler_is_not_reported_twice()
    {
        set_error_handler(function () {
        });
        error_clear_last();
        try {
            $this->io()->guard(function () {
                trigger_error('old API', E_USER_DEPRECATED);
            });
        } finally {
            restore_error_handler();
        }

        $this->assertNull(error_get_last());
    }

    /**
     * @test
     */
    public function without_a_previous_handler_php_reports_the_deprecation()
    {
        $display = ini_set('display_errors', '0');
        $log = ini_set('log_errors', '0');
        set_error_handler(null);
        error_clear_last();
        try {
            $this->io()->guard(function () {
                trigger_error('old API', E_USER_DEPRECATED);
            });
            $last = error_get_last();
        } finally {
            restore_error_handler();
            ini_set('display_errors', $display);
            ini_set('log_errors', $log);
        }

        $this->assertSame('old API', $last['message'] ?? null);
        $this->assertSame(E_USER_DEPRECATED, $last['type'] ?? null);
    }

    /**
     * @test
     */
    public function warning_still_becomes_an_exception()
    {
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('send failed');

        $this->io()->guard(function () {
            trigger_error('send failed', E_USER_WARNING);
        });
    }

    /**
     * @return AbstractIO
     */
    private function io()
    {
        return new class extends AbstractIO {
            public function __construct()
            {
            }

            public function guard(callable $call): void
            {
                $this->setErrorHandler();
                try {
                    $call();
                    $this->throwOnError();
                } finally {
                    $this->restoreErrorHandler();
                }
            }

            public function read($len)
            {
                return '';
            }

            public function write($data)
            {
            }

            public function close()
            {
            }

            protected function do_select(?int $sec, int $usec)
            {
                return 0;
            }

            public function connect()
            {
            }
        };
    }
}

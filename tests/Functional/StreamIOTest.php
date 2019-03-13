<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\TestCase;

class StreamIOTest extends TestCase
{
    /** @var array|null */
    protected $last_error;

    /**
     * @test
     */
    public function error_handler_is_restored_on_failed_connection()
    {
        $this->last_error = null;
        set_error_handler(array($this, 'custom_error_handler'));

        error_reporting(~E_NOTICE);

        $exceptionThrown = false;
        try {
            new AMQPStreamConnection(HOST, PORT - 1, USER, PASS, VHOST);
        } catch (\Exception $exception) {
            $exceptionThrown = true;
        }
        $this->assertTrue($exceptionThrown, 'Custom error handler was not set.');
        $this->assertInstanceOf('PhpAmqpLib\Exception\AMQPIOException', $exception);
        $this->assertNull($this->last_error);

        $exceptionThrown = false;
        $arr = [];

        try {
            $notice = $arr['second-key-that-does-not-exist-and-should-generate-a-notice'];
        } catch (\Exception $exception) {
            $exceptionThrown = true;
        }
        $this->assertFalse($exceptionThrown, 'Default error handler was not restored.');

        error_reporting(E_ALL);

        $previousErrorHandler = set_error_handler(array($this, 'custom_error_handler'));
        $this->assertSame('custom_error_handler', $previousErrorHandler[1]);
    }

    /**
     * @test
     */
    public function error_handler_is_restored_on_success()
    {
        set_error_handler(array($this, 'custom_error_handler'));

        new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);

        $previousErrorHandler = set_error_handler(array($this, 'custom_error_handler'));

        $this->assertSame('custom_error_handler', $previousErrorHandler[1]);
    }

    public function custom_error_handler($errno, $errstr, $errfile, $errline, $errcontext = null)
    {
        $this->last_error = compact('errno', 'errstr', 'errfile', 'errline', 'errcontext');
    }
}

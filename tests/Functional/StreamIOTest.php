<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\TestCase;

class StreamIOTest extends TestCase
{
    public function testErrorHandlerIsRestoredOnFailedConnection()
    {
        // Ignore the notice for this test.
        error_reporting(~E_NOTICE);

        $exceptionThrown = false;
        try {
            new AMQPStreamConnection('google.com', PORT, USER, PASS, VHOST);
        } catch (\ErrorException $e) {
            $exceptionThrown = true;
        }
        $this->assertTrue($exceptionThrown, 'Custom error handler was not set.');

        $exceptionThrown = false;
        $arr = array();

        try {
            $notice = $arr['second-key-that-does-not-exist-and-should-generate-a-notice'];
        } catch (\Exception $e) {
            $exceptionThrown = true;
        }
        $this->assertFalse($exceptionThrown, 'Default error handler was not restored.');

        // Set error reporting level back
        error_reporting(E_ALL);
    }
}

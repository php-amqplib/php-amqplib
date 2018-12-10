<?php
namespace PhpAmqpLib\Tests\Unit\Connection;

use PhpAmqpLib\Connection\AMQPSocketConnection;
use PHPUnit\Framework\TestCase;

class AMQPSocketConnectionTest extends TestCase
{
    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage channel RPC timeout must not be greater than I/O read timeout
     */
    public function channel_rpc_timeout_should_be_invalid_if_greater_than_read_write_timeout()
    {
        new AMQPSocketConnection(
            HOST,
            PORT,
            USER,
            PASS,
            VHOST,
            false,
            'AMQPLAIN',
            null,
            'en_US',
            3.0,
            null,
            false,
            0,
            5.0
        );
    }
}

<?php

namespace PhpAmqpLib\Tests\Unit\Connection;

use PhpAmqpLib\Connection\AMQPSocketConnection;
use PHPUnit\Framework\TestCase;

class AMQPSocketConnectionTest extends TestCase
{
    /**
     * @test
     */
    public function channel_rpc_timeout_should_be_invalid_if_greater_than_read_write_timeout()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('channel RPC timeout must not be greater than I/O read timeout');

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

<?php

namespace PhpAmqpLib\Tests\Unit\Connection;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\TestCase;

class AMQPStreamConnectionTest extends TestCase
{
    /**
     * @test
     */
    public function channel_rpc_timeout_should_be_invalid_if_greater_than_read_write_timeout()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('channel RPC timeout must not be greater than I/O read-write timeout');

        new AMQPStreamConnection(
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
            3.0,
            null,
            false,
            0,
            5.0
        );
    }
}

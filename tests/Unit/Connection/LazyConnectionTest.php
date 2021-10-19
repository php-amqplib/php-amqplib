<?php

namespace PhpAmqpLib\Tests\Unit\Connection;

use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Connection\AMQPLazySocketConnection;
use PhpAmqpLib\Connection\AMQPLazySSLConnection;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Tests\TestCaseCompat;

class LazyConnectionTest extends TestCaseCompat
{
    /**
     * @test
     */
    public function lazy_stream_connection_multiple_hosts_unsupported()
    {
        $this->expectException(\RuntimeException::class);
        AMQPLazyConnection::create_connection(
            [
                [
                    'host' => '127.0.0.1',
                ],
                [
                    'host' => '127.0.0.2',
                ],
            ]
        );
    }

    /**
     * @test
     */
    public function lazy_socket_connection_multiple_hosts_unsupported()
    {
        $this->expectException(\RuntimeException::class);
        AMQPLazySocketConnection::create_connection(
            [
                [
                    'host' => '127.0.0.1',
                ],
                [
                    'host' => '127.0.0.2',
                ],
            ]
        );
    }

    /**
     * @test
     */
    public function lazy_ssl_connection_multiple_hosts_unsupported()
    {
        $this->expectException(\RuntimeException::class);
        AMQPLazySSLConnection::create_connection(
            [
                [
                    'host' => '127.0.0.1',
                ],
                [
                    'host' => '127.0.0.2',
                ],
            ]
        );
    }
}

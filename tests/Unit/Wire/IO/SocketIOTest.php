<?php

namespace PhpAmqpLib\Tests\Unit\Wire\IO;

use PhpAmqpLib\Wire\IO\SocketIO;
use PHPUnit\Framework\TestCase;

class SocketIOTest extends TestCase
{
    /**
     * @test
     */
    public function connect()
    {
        $socketIO = new SocketIO(HOST, PORT, 1, true);
        $socketIO->connect();

        return $socketIO;
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPIOException
     */
    public function connect_with_invalid_credentials()
    {
        $socket = new SocketIO('invalid_host', 1, 1, true);

        @$socket->connect();
    }

    /**
     * @test
     * @depends connect
     * @expectedException \PhpAmqpLib\Exception\AMQPSocketException
     */
    public function read_when_closed(SocketIO $socketIO)
    {
        $socketIO->close();

        $socketIO->read(1);
    }

    /**
     * @test
     * @depends connect
     * @expectedException \PhpAmqpLib\Exception\AMQPSocketException
     */
    public function write_when_closed(SocketIO $socketIO)
    {
        $socketIO->write('data');
    }
}

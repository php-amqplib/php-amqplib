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
        $socketIO = new SocketIO(HOST, PORT, 20, true, 20, 9);
        $socketIO->connect();

        return $socketIO;
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPIOException
     */
    public function connect_with_invalid_credentials()
    {
        $socket = new SocketIO('invalid_host', 5672);
        @$socket->connect();
    }

    // TODO FUTURE re-enable test
    // php-amqplib/php-amqplib#648, php-amqplib/php-amqplib#666
    // /**
    //  * @test
    //  * @expectedException \InvalidArgumentException
    //  * @expectedExceptionMessage read_timeout must be greater than 2x the heartbeat
    //  */
    // public function read_timeout_must_be_greater_than_2x_the_heartbeat()
    // {
    //     new SocketIO('localhost', 5512, 1);
    // }
    // /**
    //  * @test
    //  * @expectedException \InvalidArgumentException
    //  * @expectedExceptionMessage send_timeout must be greater than 2x the heartbeat
    //  */
    // public function send_timeout_must_be_greater_than_2x_the_heartbeat()
    // {
    //     new SocketIO('localhost', '5512', 30, true, 20, 10);
    // }

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

<?php

namespace PhpAmqpLib\Tests\Unit\Wire\IO;

use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Wire\IO\SocketIO;
use PHPUnit\Framework\TestCase;

/**
 * @group connection
 */
class SocketIOTest extends TestCase
{
    /**
     * @test
     */
    public function connect()
    {
        $socketIO = new SocketIO(HOST, PORT, 20, true, 20, 9);
        $socketIO->connect();
        $ready = $socketIO->select(0, 0);
        $this->assertEquals(0, $ready);

        return $socketIO;
    }

    /**
     * @test
     */
    public function connect_ipv6()
    {
        $socketIO = new SocketIO(HOST6, PORT, 20, true, 20, 9);
        $socketIO->connect();
        $ready = $socketIO->select(0, 0);
        $this->assertEquals(0, $ready);
    }

    /**
     * @test
     */
    public function connect_with_invalid_credentials()
    {
        $this->expectException(\PhpAmqpLib\Exception\AMQPIOException::class);

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
     */
    public function read_when_closed(SocketIO $socketIO)
    {
        $this->expectException(\PhpAmqpLib\Exception\AMQPSocketException::class);

        $socketIO->close();

        $socketIO->read(1);
    }

    /**
     * @test
     * @depends connect
     */
    public function write_when_closed(SocketIO $socketIO)
    {
        $this->expectException(\PhpAmqpLib\Exception\AMQPSocketException::class);

        $socketIO->write('data');
    }

    /**
     * @test
     * @group linux
     * @requires OS Linux
     */
    public function select_must_throw_io_exception()
    {
        $this->expectException(AMQPConnectionClosedException::class);
        $property = new \ReflectionProperty(SocketIO::class, 'sock');
        $property->setAccessible(true);

        $socket = new SocketIO('0.0.0.0', PORT, 0.1, false, 0.1, 0);
        $property->setValue($socket, null);

        $socket->select(0, 0);
    }
}

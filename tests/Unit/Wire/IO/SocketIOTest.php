<?php
namespace PhpAmqpLib\Tests\Unit\Wire\IO;

use PhpAmqpLib\Wire\IO\SocketIO;
use PHPUnit\Framework\TestCase;

class SocketIOTest extends TestCase
{
    public function testConnect()
    {
        $socketIO = new SocketIO(HOST,PORT,1,true);
        $socketIO->connect();

        return $socketIO;
    }

    /**
     * @expectedException \PhpAmqpLib\Exception\AMQPIOException
     */
    public function testConnectWithInValidCredentials()
    {
        \PHPUnit_Framework_Error_Warning::$enabled = false;

        $socket = new SocketIO('invalid_host',1,1,true);
        $socket->connect();
        $socket->close();
        $socket->read(1);

    }

    /**
     * @depends testConnect
     * @expectedException \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function testReadWhenClosed(SocketIO $socketIO)
    {
        \PHPUnit_Framework_Error_Warning::$enabled = false;

        $socketIO->close();
        $socketIO->read(1);
    }

    /**
     * @depends testConnect
     * @expectedException \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function testWriteWhenClosed(SocketIO $socketIO)
    {
        \PHPUnit_Framework_Error_Warning::$enabled = false;
        $socketIO->write('data');
    }
}

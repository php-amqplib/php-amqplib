<?php

namespace PhpAmqpLib\Tests\Functional\Connection;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;
use PhpAmqpLib\Wire\AMQPBufferReader;
use PhpAmqpLib\Wire\AMQPWriter;

/**
 * @group connection
 */
class ConnectionCreationTest extends AbstractConnectionTest
{
    public function hostDataProvider(): array
    {
        return array(
            'plain' => array(
                array(
                    array('host' => HOST, 'port' => PORT, 'user' => USER, 'password' => PASS),
                    array('host' => HOST, 'port' => PORT, 'user' => USER, 'password' => PASS)
                )
            ),
            'keys' => array(
                array(
                    'host1' => array('host' => HOST, 'port' => PORT, 'user' => USER, 'password' => PASS),
                    'host2' => array('host' => HOST, 'port' => PORT, 'user' => USER, 'password' => PASS)
                )
            )
        );
    }

    /**
     * @test
     * @dataProvider hostDataProvider
     * @covers \PhpAmqpLib\Connection\AbstractConnection::create_connection()
     */
    public function create_connection(array $hosts)
    {
        $conn = AMQPStreamConnection::create_connection($hosts);
        $this->assertInstanceOf(AMQPStreamConnection::class, $conn);
    }

    /**
     * @test
     * @testWith [0, 0, 0]
     *           [0, 10, 0]
     *           [10, 0, 10]
     *           [10, 20, 10]
     *           [20, 10, 10]
     * @covers \PhpAmqpLib\Connection\AbstractConnection::__construct()
     * @covers \PhpAmqpLib\Connection\AbstractConnection::connection_tune()
     */
    public function heartbeat_negotiation(int $client, int $broker, int $expected)
    {
        $class = new \ReflectionClass(AbstractConnection::class);
        $method = $class->getMethod('connection_tune');
        $method->setAccessible(true);

        $writer = new AMQPWriter();
        $writer->write_short(0);
        $writer->write_long(0);
        $writer->write_short($broker); // broker heartbeat
        $args = new AMQPBufferReader($writer->getvalue());

        $connection = $this->connection_create('stream', HOST, PORT, ['heartbeat' => $client]);
        $method->invoke($connection, $args);
        self::assertEquals($expected, $connection->getHeartbeat());
    }
}

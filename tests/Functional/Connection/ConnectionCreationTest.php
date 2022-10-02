<?php

namespace PhpAmqpLib\Tests\Functional\Connection;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;

/**
 * @group connection
 */
class ConnectionCreationTest extends AbstractConnectionTest
{

    public function hostDataProvider()
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
        $conn = AMQPStreamConnection::create_connection($hosts, array());
        $this->assertInstanceOf('PhpAmqpLib\Connection\AMQPStreamConnection', $conn);
    }

    /**
     * @test
     * @covers \PhpAmqpLib\Connection\AbstractConnection::__construct()
     * @covers \PhpAmqpLib\Connection\AbstractConnection::connection_tune()
     */
    public function heartbeat_negotiation()
    {
        $connection = $this->conection_create();
        $serverHeartBeat = $connection->getHeartbeat();
        $connection->close();
        unset($connection);

        if ($serverHeartBeat > 0) {
            // try to negotiate lower value
            $connection = $this->conection_create('stream', HOST, PORT, ['heartbeat' => $serverHeartBeat - 1]);
            self::assertLessThan($serverHeartBeat, $connection->getHeartbeat());
        } else {
            // try to negotiate higher value
            $connection = $this->conection_create('stream', HOST, PORT, ['heartbeat' => 30]);
            self::assertEquals(30, $connection->getHeartbeat());
        }
        $connection->close();
    }
}

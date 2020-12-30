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
     * @covers \PhpAmqpLib\Connection\AbstractConnection::createConnection()
     */
    public function createConnection(array $hosts)
    {
        $conn = AMQPStreamConnection::createConnection($hosts, array());
        $this->assertInstanceOf('PhpAmqpLib\Connection\AMQPStreamConnection', $conn);
    }
}

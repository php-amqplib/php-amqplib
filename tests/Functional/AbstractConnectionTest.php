<?php

namespace PhpAmqpLib\Tests\Functional;

use Httpful\Request;
use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PHPUnit\Framework\TestCase;

abstract class AbstractConnectionTest extends TestCase
{
    public static $blocked = false;

    /**
     * @param string $type
     * @param string $host
     * @param string $port
     * @param array $options
     * @return AbstractConnection
     */
    protected function conection_create($type = 'stream', $host = HOST, $port = PORT, $options = array())
    {
        $keepalive = isset($options['keepalive']) ? $options['keepalive'] : false;
        $heartbeat = isset($options['heartbeat']) ? $options['heartbeat'] : 0;
        $timeout = isset($options['timeout']) ? $options['timeout'] : 1;

        switch ($type) {
            case 'stream':
                $connection = new AMQPStreamConnection(
                    $host,
                    $port,
                    USER,
                    PASS,
                    VHOST,
                    false,
                    'AMQPLAIN',
                    null,
                    'en_US',
                    $timeout,
                    $timeout,
                    null,
                    $keepalive,
                    $heartbeat
                );
                break;
            case 'socket':
                $connection = new AMQPSocketConnection(
                    $host,
                    $port,
                    USER,
                    PASS,
                    VHOST,
                    false,
                    'AMQPLAIN',
                    null,
                    'en_US',
                    $timeout,
                    $keepalive,
                    $timeout,
                    $heartbeat
                );
                break;
            default:
        }

        $this->assertTrue($connection->isConnected());

        return $connection;
    }

    protected function queue_bind(AMQPChannel $channel, $exchange_name, &$queue_name)
    {
        $channel->exchange_declare($exchange_name, AMQPExchangeType::DIRECT);
        list($queue_name, ,) = $channel->queue_declare();
        $channel->queue_bind($queue_name, $exchange_name, $queue_name);
    }

    /**
     * @param string $name
     * @return ToxiProxy
     */
    protected function create_proxy($name = 'amqp_connection')
    {
        $proxy = new ToxiProxy($name, $this->get_toxiproxy_host());
        $proxy->open(HOST, PORT, $this->get_toxiproxy_amqp_port());

        return $proxy;
    }

    protected function get_toxiproxy_host()
    {
        $host = getenv('TOXIPROXY_HOST');
        if (!$host) {
            $this->markTestSkipped('TOXIPROXY_HOST is not set');
        }

        return $host;
    }

    protected function get_toxiproxy_amqp_port()
    {
        $port = getenv('TOXIPROXY_AMQP_PORT');
        if (!$port) {
            $this->markTestSkipped('TOXIPROXY_AMQP_PORT is not set');
        }

        return $port;
    }

    protected function assertConnectionClosed(AbstractConnection $connection)
    {
        $this->assertFalse($connection->isConnected());
        $this->assertNotNull($connection->getIO());
        $this->assertNull($connection->getIO()->getSocket());
        // all channels must be closed
        foreach ($connection->channels as $ch) {
            if ($ch instanceof AMQPChannel) {
                $this->assertFalse($ch->is_open());
            }
            if ($ch instanceof AbstractConnection) {
                $this->assertFalse($ch->isConnected());
            }
        }
        $this->assertNotEmpty($connection->channels);
    }

    protected function assertChannelClosed(AbstractChannel $channel)
    {
        $this->assertFalse($channel->is_open());
        $this->assertEmpty($channel->callbacks);
    }
}

// mock low level IO write functions
namespace PhpAmqpLib\Wire\IO;

function fwrite() {
    if (\PhpAmqpLib\Tests\Functional\AbstractConnectionTest::$blocked) {
        return 0;
    }

    return call_user_func_array('\fwrite', func_get_args());
}

namespace PhpAmqpLib\Wire\IO;

function socket_write() {
    if (\PhpAmqpLib\Tests\Functional\AbstractConnectionTest::$blocked) {
        return 0;
    }

    return call_user_func_array('\socket_write', func_get_args());
}

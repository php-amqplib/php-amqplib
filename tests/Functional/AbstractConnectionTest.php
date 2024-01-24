<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Tests\TestCaseCompat;

abstract class AbstractConnectionTest extends TestCaseCompat
{
    public static $blocked = false;

    protected function connection_create(
        string $type = 'stream',
        string $host = HOST,
        int $port = PORT,
        array $options = array()
    ): AbstractConnection {
        $timeout = $options['timeout'] ?? 1;
        $lazy = $options['lazy'] ?? false;
        $config = new AMQPConnectionConfig();
        $config->setIsLazy($lazy);
        if ($type === 'ssl') {
            $config->setIoType(AMQPConnectionConfig::IO_TYPE_STREAM);
            $config->setIsSecure(true);
            $config->setNetworkProtocol($options['protocol'] ?? 'ssl');
            $config->setSslCryptoMethod($options['ssl']['crypto_method'] ?? null);
            $config->setSslCaCert($options['ssl']['cafile'] ?? null);
            $config->setSslCaPath($options['ssl']['capath'] ?? null);
            $config->setSslCert($options['ssl']['local_cert'] ?? null);
            $config->setSslKey($options['ssl']['local_pk'] ?? null);
            $config->setSslVerify($options['ssl']['verify_peer'] ?? null);
            $config->setSslVerifyName($options['ssl']['verify_peer_name'] ?? null);
            $config->setSslPassPhrase($options['ssl']['passphrase'] ?? null);
            $config->setSslCiphers($options['ssl']['ciphers'] ?? null);
        } else {
            $config->setIoType($type);
        }
        $config->setHost($host);
        $config->setPort($port);
        $config->setKeepalive($options['keepalive'] ?? false);
        $config->setHeartbeat($options['heartbeat'] ?? 0);
        $config->setReadTimeout($timeout);
        $config->setWriteTimeout($timeout);
        $config->setConnectionTimeout($options['connectionTimeout'] ?? $timeout);
        $config->setSendBufferSize(16384);

        $connection = AMQPConnectionFactory::create($config);
        if (!$lazy) {
            $this->assertTrue($connection->isConnected());
        }

        return $connection;
    }

    protected function queue_bind(AMQPChannel $channel, $exchange_name, &$queue_name)
    {
        $channel->exchange_declare($exchange_name, AMQPExchangeType::DIRECT);
        list($queue_name, ,) = $channel->queue_declare();
        $channel->queue_bind($queue_name, $exchange_name, $queue_name);
    }

    /**
     * @param string $connectionType
     * @param array $options
     * @return AMQPChannel
     */
    protected function channel_create($connectionType, $options = [])
    {
        $connection = $this->connection_create($connectionType, HOST, PORT, $options);
        $channel = $connection->channel();
        $this->assertTrue($channel->is_open());

        return $channel;
    }

    /**
     * @param string $name
     * @return ToxiProxy
     */
    protected function create_proxy($name = 'amqp_connection')
    {
        $host = trim(getenv('TOXIPROXY_AMQP_TARGET'));
        if (empty($host)) {
            $host = HOST;
        }
        $proxy = new ToxiProxy($name, $this->get_toxiproxy_host());
        $proxy->open($host, PORT, $this->get_toxiproxy_amqp_port());

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

function fwrite()
{
    if (\PhpAmqpLib\Tests\Functional\AbstractConnectionTest::$blocked) {
        return 0;
    }

    return call_user_func_array('\fwrite', func_get_args());
}

namespace PhpAmqpLib\Wire\IO;

function socket_write()
{
    if (\PhpAmqpLib\Tests\Functional\AbstractConnectionTest::$blocked) {
        return 0;
    }

    return call_user_func_array('\socket_write', func_get_args());
}

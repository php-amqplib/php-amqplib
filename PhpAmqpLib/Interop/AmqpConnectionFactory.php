<?php

namespace PhpAmqpLib\Interop;

use Interop\Amqp\AmqpConnectionFactory as InteropAmqpConnectionFactory;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Connection\AMQPLazySocketConnection;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class AmqpConnectionFactory implements InteropAmqpConnectionFactory
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var AbstractConnection
     */
    private $connection;

    /**
     * The config could be an array, string DSN or null. In case of null it will attempt to connect to localhost with default credentials.
     *
     * [
     *     'host'  => 'amqp.host The host to connect too. Note: Max 1024 characters.',
     *     'port'  => 'amqp.port Port on the host.',
     *     'vhost' => 'amqp.vhost The virtual host on the host. Note: Max 128 characters.',
     *     'user' => 'amqp.user The user name to use. Note: Max 128 characters.',
     *     'pass' => 'amqp.password Password. Note: Max 128 characters.',
     *     'lazy' => 'the connection will be performed as later as possible, if the option set to true',
     *     'stream' => 'stream or socket connection',
     *     'receive_method' => 'Could be either basic_get or basic_consume',
     * ]
     *
     * or
     *
     * amqp://user:pass@host:10000/vhost?lazy=true&socket=true
     *
     * @param array|string $config
     */
    public function __construct($config = 'amqp://')
    {
        if (empty($config) || 'amqp://' === $config) {
            $config = [];
        } elseif (is_string($config)) {
            $config = $this->parseDsn($config);
        } elseif (is_array($config)) {
        } else {
            throw new \LogicException('The config must be either an array of options, a DSN string or null');
        }

        $this->config = array_replace($this->defaultConfig(), $config);

        $supportedMethods = ['basic_get', 'basic_consume'];
        if (false == in_array($this->config['receive_method'], $supportedMethods, true)) {
            throw new \LogicException(sprintf(
                'Invalid "receive_method" option value "%s". It could be only "%s"',
                $this->config['receive_method'],
                implode('", "', $supportedMethods)
            ));
        }
    }

    /**
     * @return AmqpContext
     */
    public function createContext()
    {
        return new AmqpContext($this->establishConnection(), $this->config['receive_method']);
    }

    /**
     * @return AbstractConnection
     */
    private function establishConnection()
    {
        if (false == $this->connection) {
            if ($this->config['stream']) {
                if ($this->config['lazy']) {
                    $con = new AMQPLazyConnection(
                        $this->config['host'],
                        $this->config['port'],
                        $this->config['user'],
                        $this->config['pass'],
                        $this->config['vhost'],
                        $this->config['insist'],
                        $this->config['login_method'],
                        $this->config['login_response'],
                        $this->config['locale'],
                        $this->config['connection_timeout'],
                        $this->config['read_write_timeout'],
                        null,
                        $this->config['keepalive'],
                        $this->config['heartbeat']
                    );
                } else {
                    $con = new AMQPStreamConnection(
                        $this->config['host'],
                        $this->config['port'],
                        $this->config['user'],
                        $this->config['pass'],
                        $this->config['vhost'],
                        $this->config['insist'],
                        $this->config['login_method'],
                        $this->config['login_response'],
                        $this->config['locale'],
                        $this->config['connection_timeout'],
                        $this->config['read_write_timeout'],
                        null,
                        $this->config['keepalive'],
                        $this->config['heartbeat']
                    );
                }
            } else {
                if ($this->config['lazy']) {
                    $con = new AMQPLazySocketConnection(
                        $this->config['host'],
                        $this->config['port'],
                        $this->config['user'],
                        $this->config['pass'],
                        $this->config['vhost'],
                        $this->config['insist'],
                        $this->config['login_method'],
                        $this->config['login_response'],
                        $this->config['locale'],
                        $this->config['read_timeout'],
                        $this->config['keepalive'],
                        $this->config['write_timeout'],
                        $this->config['heartbeat']
                    );
                } else {
                    $con = new AMQPSocketConnection(
                        $this->config['host'],
                        $this->config['port'],
                        $this->config['user'],
                        $this->config['pass'],
                        $this->config['vhost'],
                        $this->config['insist'],
                        $this->config['login_method'],
                        $this->config['login_response'],
                        $this->config['locale'],
                        $this->config['read_timeout'],
                        $this->config['keepalive'],
                        $this->config['write_timeout'],
                        $this->config['heartbeat']
                    );
                }
            }

            $this->connection = $con;
        }

        return $this->connection;
    }

    /**
     * @param string $dsn
     *
     * @return array
     */
    private function parseDsn($dsn)
    {
        $dsnConfig = parse_url($dsn);
        if (false === $dsnConfig) {
            throw new \LogicException(sprintf('Failed to parse DSN "%s"', $dsn));
        }

        $dsnConfig = array_replace([
            'scheme' => null,
            'host' => null,
            'port' => null,
            'user' => null,
            'pass' => null,
            'path' => null,
            'query' => null,
        ], $dsnConfig);

        if ('amqp' !== $dsnConfig['scheme']) {
            throw new \LogicException(sprintf('The given DSN scheme "%s" is not supported. Could be "amqp" only.', $dsnConfig['scheme']));
        }

        if ($dsnConfig['query']) {
            $query = [];
            parse_str($dsnConfig['query'], $query);

            $dsnConfig = array_replace($query, $dsnConfig);
        }

        $dsnConfig['vhost'] = ltrim($dsnConfig['path'], '/');

        unset($dsnConfig['scheme'], $dsnConfig['query'], $dsnConfig['fragment'], $dsnConfig['path']);

        $dsnConfig = array_map(function ($value) {
            return urldecode($value);
        }, $dsnConfig);

        return $dsnConfig;
    }

    /**
     * @return array
     */
    private function defaultConfig()
    {
        return [
            'stream' => true,
            'lazy' => true,
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'pass' => 'guest',
            'vhost' => '/',
            'insist' => false,
            'login_method' => 'AMQPLAIN',
            'login_response' => null,
            'locale' => 'en_US',
            'read_timeout' => 3,
            'keepalive' => false,
            'write_timeout' => 3,
            'heartbeat' => 0,
            'connection_timeout' => 3.0,
            'read_write_timeout' => 3.0,
            'receive_method' => 'basic_get',
        ];
    }
}

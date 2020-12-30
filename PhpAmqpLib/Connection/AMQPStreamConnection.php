<?php

namespace PhpAmqpLib\Connection;

use PhpAmqpLib\Wire\IO\StreamIO;

class AMQPStreamConnection extends AbstractConnection
{
    /**
     * @param string $host
     * @param string $port
     * @param string $user
     * @param string $password
     * @param string $vhost
     * @param bool $insist
     * @param string $loginMethod
     * @param null $loginResponse @deprecated
     * @param string $locale
     * @param float $connectionTimeout
     * @param float $read_write_timeout
     * @param null $context
     * @param bool $keepalive
     * @param int $heartbeat
     * @param float $channel_rpc_timeout
     * @param string|null $ssl_protocol
     */
    public function __construct(
        $host,
        $port,
        $user,
        $password,
        $vhost = '/',
        $insist = false,
        $loginMethod = 'AMQPLAIN',
        $loginResponse = null,
        $locale = 'en_US',
        $connectionTimeout = 3.0,
        $read_write_timeout = 3.0,
        $context = null,
        $keepalive = false,
        $heartbeat = 0,
        $channel_rpc_timeout = 0.0,
        $ssl_protocol = null
    ) {
        if ($channel_rpc_timeout > $read_write_timeout) {
            throw new \InvalidArgumentException('channel RPC timeout must not be greater than I/O read-write timeout');
        }

        $io = new StreamIO(
            $host,
            $port,
            $connectionTimeout,
            $read_write_timeout,
            $context,
            $keepalive,
            $heartbeat,
            $ssl_protocol
        );

        parent::__construct(
            $user,
            $password,
            $vhost,
            $insist,
            $loginMethod,
            $loginResponse,
            $locale,
            $io,
            $heartbeat,
            $connectionTimeout,
            $channel_rpc_timeout
        );

        // save the params for the use of __clone, this will overwrite the parent
        $this->constructParams = func_get_args();
    }

    protected static function tryCreateConnection($host, $port, $user, $password, $vhost, $options)
    {
        $insist = isset($options['insist']) ?
                        $options['insist'] : false;
        $loginMethod = isset($options['loginMethod']) ?
                              $options['loginMethod'] : 'AMQPLAIN';
        $loginResponse = isset($options['loginResponse']) ?
                                $options['loginResponse'] : null;
        $locale = isset($options['locale']) ?
                        $options['locale'] : 'en_US';
        $connectionTimeout = isset($options['connection_timeout']) ?
                                    $options['connection_timeout'] : 3.0;
        $read_write_timeout = isset($options['read_write_timeout']) ?
                                    $options['read_write_timeout'] : 130.0;
        $context = isset($options['context']) ?
                         $options['context'] : null;
        $keepalive = isset($options['keepalive']) ?
                           $options['keepalive'] : false;
        $heartbeat = isset($options['heartbeat']) ?
                           $options['heartbeat'] : 60;
        return new static(
            $host,
            $port,
            $user,
            $password,
            $vhost,
            $insist,
            $loginMethod,
            $loginResponse,
            $locale,
            $connectionTimeout,
            $read_write_timeout,
            $context,
            $keepalive,
            $heartbeat
        );
    }
}

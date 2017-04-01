<?php
namespace PhpAmqpLib\Connection;

use PhpAmqpLib\Wire\IO\SocketIO;

class AMQPSocketConnection extends AbstractConnection
{
    /**
     * @param string    $host
     * @param int       $port
     * @param string    $user
     * @param string    $password
     * @param string    $vhost
     * @param bool      $insist
     * @param string    $login_method
     * @param null      $login_response
     * @param string    $locale
     * @param float|int $timeout
     * @param bool      $keepalive
     * @param int       $read_write_timeout
     * @param int       $heartbeat
     */
    public function __construct(
        $host,
        $port,
        $user,
        $password,
        $vhost = '/',
        $insist = false,
        $login_method = 'AMQPLAIN',
        $login_response = null,
        $locale = 'en_US',
        $timeout = 3,
        $keepalive = false,
        $read_write_timeout = 3,
        $heartbeat = 0
    ) {
        $io = new SocketIO($host, $port, $timeout, $keepalive, $read_write_timeout, $heartbeat);

        parent::__construct(
            $user,
            $password,
            $vhost,
            $insist,
            $login_method,
            $login_response,
            $locale,
            $io,
            $heartbeat
        );
    }
}

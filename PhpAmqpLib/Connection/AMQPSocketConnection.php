<?php
namespace PhpAmqpLib\Connection;

use PhpAmqpLib\Wire\IO\SocketIO;

class AMQPSocketConnection extends AbstractConnection
{
    /**
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     * @param string $vhost
     * @param bool $insist
     * @param string $login_method
     * @param null $login_response
     * @param string $locale
     * @param float $read_timeout
     * @param bool $keepalive
     * @param int $write_timeout
     * @param int $heartbeat
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
        $read_timeout = 3,
        $keepalive = false,
        $write_timeout = 3,
        $heartbeat = 0
    ) {
        $io = new SocketIO($host, $port, $read_timeout, $keepalive, $write_timeout, $heartbeat);

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

    protected static function try_create_connection($host, $port, $user, $password, $vhost, $options){
        $insist = isset($options['insist']) ?
                        $options['insist'] : false;
        $login_method = isset($options['login_method']) ?
                              $options['login_method'] :'AMQPLAIN';
        $login_response = isset($options['login_response']) ?
                                $options['login_response'] : null;
        $locale = isset($options['locale']) ?
                        $options['locale'] : 'en_US';
        $read_timeout = isset($options['read_timeout']) ?
                              $options['read_timeout'] : 3;
        $keepalive = isset($options['keepalive']) ?
                           $options['keepalive'] : false;
        $write_timeout = isset($options['write_timeout']) ?
                               $options['write_timeout'] : 3;
        $heartbeat = isset($options['heartbeat']) ?
                           $options['heartbeat'] : 0;
        return new static($host,
                          $port,
                          $user,
                          $password,
                          $vhost,
                          $insist,
                          $login_method,
                          $login_response,
                          $locale,
                          $read_timeout,
                          $keepalive,
                          $write_timeout,
                          $heartbeat);
    }
}

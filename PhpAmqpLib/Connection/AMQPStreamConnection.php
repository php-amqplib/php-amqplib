<?php

namespace PhpAmqpLib\Connection;

use PhpAmqpLib\Wire\IO\StreamIO;

class AMQPStreamConnection extends AbstractConnection
{

    public function __construct(
        $host,
        $port,
        $user,
        $password,
        $vhost = "/",
        $insist = false,
        $login_method = "AMQPLAIN",
        $login_response = null,
        $locale = "en_US",
        $connection_timeout = 3,
        $read_write_timeout = 3,
        $context = null,
        $keepalive = false,
        $heartbeat = 0
    ) {
        $io = new StreamIO($host, $port, $connection_timeout, $read_write_timeout, $context, $keepalive, $heartbeat);
        $this->sock = $io->get_socket();

        parent::__construct($user, $password, $vhost, $insist, $login_method, $login_response, $locale, $io, $heartbeat);

        // save the params for the use of __clone, this will overwrite the parent
        $this->construct_params = func_get_args();
    }

}

<?php

namespace PhpAmqpLib\Connection;

class AMQPLazyConnection extends AMQPConnection
{
    protected $sock = null;

    private $host;
    private $port;
    private $user;
    private $password;
    private $vhost;
    private $insist;
    private $login_method;
    private $login_response;
    private $locale;
    private $connection_timeout;
    private $read_write_timeout;
    private $context;

    function __construct(
        $host,
        $port,
        $user,
        $password,
        $vhost="/",
        $insist=false,
        $login_method="AMQPLAIN",
        $login_response=null,
        $locale="en_US",
        $connection_timeout = 3,
        $read_write_timeout = 3,
        $context = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->vhost = $vhost;
        $this->insist = $insist;
        $this->login_method = $login_method;
        $this->login_response = $login_response;
        $this->locale = $locale;
        $this->connection_timeout = $connection_timeout;
        $this->read_write_timeout = $read_write_timeout;
        $this->context = $context;
    }


    /**
     * get socket from current connection
     *
     * @deprecated
     */
    public function getSocket()
    {
        $this->initLazyConnection();

        return $this->sock;
    }

    /**
     * @inheritdoc
     */
    public function channel($channel_id = null)
    {
        $this->initLazyConnection();

        return parent::channel($channel_id);
    }

    /**
     * @return null|\PhpAmqpLib\Wire\IO\AbstractIO
     */
    protected function getIO()
    {
        $this->initLazyConnection();

        return $this->io;
    }

    /**
     * Initialize the lazy connection if not initialized yet
     */
    private function initLazyConnection()
    {
        if (is_null($this->io)) {
            parent::__construct(
                $this->host,
                $this->port,
                $this->user,
                $this->password,
                $this->vhost,
                $this->insist,
                $this->login_method,
                $this->login_response,
                $this->locale,
                $this->connection_timeout,
                $this->read_write_timeout,
                $this->context
            );
        }
    }

}

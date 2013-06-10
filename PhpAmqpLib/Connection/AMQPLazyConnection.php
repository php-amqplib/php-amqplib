<?php

namespace PhpAmqpLib\Connection;

class AMQPLazyConnection extends AMQPConnection
{
    /**
     * Connect to AMQP server on construct?
     * @var bool
     */
    protected static $connect_on_construct = false;

    /**
     * get socket from current connection
     *
     * @deprecated
     */
    public function getSocket()
    {
        $this->connect();

        return $this->sock;
    }

    /**
     * @inheritdoc
     */
    public function channel($channel_id = null)
    {
        $this->connect();

        return parent::channel($channel_id);
    }

    /**
     * @return null|\PhpAmqpLib\Wire\IO\AbstractIO
     */
    protected function getIO()
    {
        $this->connect();

        return $this->io;
    }
}

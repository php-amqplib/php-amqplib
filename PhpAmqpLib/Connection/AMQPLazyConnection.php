<?php

namespace PhpAmqpLib\Connection;

class AMQPLazyConnection extends AMQPStreamConnection
{
    /**
     * Gets socket from current connection
     *
     * @deprecated
     */
    public function getSocket()
    {
        $this->connect();

        return parent::getSocket();
    }

    /**
     * {@inheritdoc}
     */
    public function channel($channelId = null)
    {
        $this->connect();

        return parent::channel($channelId);
    }

    /**
     * @return null|\PhpAmqpLib\Wire\IO\AbstractIO
     */
    public function getIO()
    {
        if (empty($this->io)) {
            $this->connect();
        }

        return $this->io;
    }

    /**
     * Should the connection be attempted during construction?
     *
     * @return bool
     */
    public function connectOnConstruct()
    {
        return false;
    }
}

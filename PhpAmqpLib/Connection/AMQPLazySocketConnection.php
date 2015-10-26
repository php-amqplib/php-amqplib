<?php

namespace PhpAmqpLib\Connection;

/**
 * Yet another lazy connection. This time using sockets. Current architecture doesn't allow to wrap existing connections
 */
class AMQPLazySocketConnection extends AMQPSocketConnection
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
        if (!$this->io) {
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
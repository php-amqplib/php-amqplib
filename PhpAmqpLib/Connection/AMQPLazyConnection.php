<?php

namespace PhpAmqpLib\Connection;

class AMQPLazyConnection extends AMQPConnection
{

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
        if (!$this->io) {
            $this->connect();
        }

        return $this->io;
    }



    /**
     * Should the connection be attempted during construction?
     * @return bool
     */
    public function connectOnConstruct()
    {
        return false;
    }

}

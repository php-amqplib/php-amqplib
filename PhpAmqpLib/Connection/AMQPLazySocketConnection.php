<?php

namespace PhpAmqpLib\Connection;

/**
 * Yet another lazy connection. This time using sockets. Current architecture doesn't allow to wrap existing connections
 */
class AMQPLazySocketConnection extends AMQPSocketConnection
{
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

    /**
     * @param string[][] $hosts
     * @param string[] $options
     * @return self
     * @throws \Exception
     * @deprecated Use ConnectionFactory
     */
    public static function create_connection($hosts, $options = array())
    {
        if (count($hosts) > 1) {
            throw new \RuntimeException('Lazy connection does not support multiple hosts');
        }

        return parent::create_connection($hosts, $options);
    }
}

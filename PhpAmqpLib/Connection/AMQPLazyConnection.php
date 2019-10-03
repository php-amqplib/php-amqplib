<?php
namespace PhpAmqpLib\Connection;

use PhpAmqpLib\Wire\IO\StreamIO;
use PhpAmqpLib\Exception\AMQPIOException;

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

    /** keep track of the original hosts configuration */
    private static $hosts = [];
    private static $options = [];
    public static function create_connection($hosts, $options = array())
    {
        self::$hosts = $hosts;
        self::$options = $options;

        return parent::create_connection($hosts, $options);
    }

    /**
     * When performing an actual connect, allow cluster support for different nodes
     *
     * Known limitation: cluster nodes require the same user, pass and vhost since we are only reinitializing the
     * IO-layer here, not the connection object
     */
    protected function connect()
    {
        if ($this->isConnected()) {
            return;
        }

        // only when using AbstractConnection::create_connection for cluster support, allow multiple hosts
        if (!self::$hosts)
        {
            return parent::connect();
        }

        $latest_exception = null;
        foreach (self::$hosts as $hostdef) {
            try {
                AbstractConnection::validate_host($hostdef);
                $host = $hostdef['host'];
                $port = $hostdef['port'];

                $this->io = new StreamIO(
                    $host,
                    $port,
                    isset($this->construct_params[9]) ? $this->construct_params[9] : 3.0,
                    isset($this->construct_params[10]) ? $this->construct_params[10] : 3.0,
                    isset($this->construct_params[11]) ? $this->construct_params[11] : null,
                    isset($this->construct_params[12]) ? $this->construct_params[12] : false,
                    isset($this->construct_params[13]) ? $this->construct_params[13] : 0,
                    isset($this->construct_params[15]) ? $this->construct_params[15] : null
                );

                return parent::connect();
            } catch(AMQPIOException $e) {
                $latest_exception = $e;
            }
        }
        throw $latest_exception;
    }
}

<?php

namespace PhpAmqpLib\Helper;

/**
 * @property-read int $SOCKET_EPIPE
 * @property-read int $SOCKET_ENETDOWN
 * @property-read int $SOCKET_ENETUNREACH
 * @property-read int $SOCKET_ENETRESET
 * @property-read int $SOCKET_ECONNABORTED
 * @property-read int $SOCKET_ECONNRESET
 * @property-read int $SOCKET_ECONNREFUSED
 * @property-read int $SOCKET_ETIMEDOUT
 * @property-read int $SOCKET_EWOULDBLOCK
 * @property-read int $SOCKET_EINTR
 * @property-read int $SOCKET_EAGAIN
 */
final class SocketConstants
{
    /**
     * @var int[]
     */
    private $constants;

    /** @var self */
    private static $instance;

    public function __construct()
    {
        $constants = get_defined_constants(true);
        if (isset($constants['sockets'])) {
            $this->constants = $constants['sockets'];
        } else {
            trigger_error('Sockets extension is not enabled', E_USER_WARNING);
            $this->constants = array();
        }
    }

    /**
     * @param string $name
     * @return int
     */
    public function __get($name)
    {
        return isset($this->constants[$name]) ? $this->constants[$name] : 0;
    }

    /**
     * @param string $name
     * @param int $value
     * @internal
     */
    public function __set($name, $value)
    {
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->constants[$name]);
    }

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }
}

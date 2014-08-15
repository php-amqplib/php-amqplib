<?php

namespace PhpAmqpLib\Wire\IO;

use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPRuntimeException;

class SocketIO extends AbstractIO
{

    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var int
     */
    protected $timeout;

    /**
     * @var resource
     */
    private $sock;



    public function __construct($host, $port, $timeout, $keepalive = false)
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->keepalive = $keepalive;
    }



    /**
     * Setup the socket connection
     *
     * @throws \Exception
     */
    public function connect()
    {
        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        socket_set_option($this->sock, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $this->timeout, 'usec' => 0));
        socket_set_option($this->sock, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $this->timeout, 'usec' => 0));

        if (!socket_connect($this->sock, $this->host, $this->port)) {
            $errno = socket_last_error($this->sock);
            $errstr = socket_strerror($errno);
            throw new AMQPIOException("Error Connecting to server($errno): $errstr ", $errno);
        }

        socket_set_block($this->sock);
        socket_set_option($this->sock, SOL_TCP, TCP_NODELAY, 1);

        if ($this->keepalive) {
            $this->enable_keepalive();
        }
    }



    /**
     * Reconnect the socket
     */
    public function reconnect()
    {
        $this->close();
        $this->connect();
    }



    public function read($n)
    {
        $res = '';
        $read = 0;

        $buf = socket_read($this->sock, $n);
        while ($read < $n && $buf !== '' && $buf !== false) {
            // Null sockets are invalid, throw exception
            if (is_null($this->sock)) {
                throw new AMQPRuntimeException("Socket was null! Last SocketError was: "
                    . socket_strerror(socket_last_error()));
            }

            $read += mb_strlen($buf, 'ASCII');
            $res .= $buf;
            $buf = socket_read($this->sock, $n - $read);
        }

        if (mb_strlen($res, 'ASCII') != $n) {
            throw new AMQPIOException("Error reading data. Received " .
                mb_strlen($res, 'ASCII') . " instead of expected $n bytes");
        }

        return $res;
    }



    public function write($data)
    {
        $len = mb_strlen($data, 'ASCII');

        while (true) {
            // Null sockets are invalid, throw exception
            if (is_null($this->sock)) {
                throw new AMQPRuntimeException("Socket was null! Last SocketError was: "
                    . socket_strerror(socket_last_error()));
            }

            $sent = socket_write($this->sock, $data, $len);
            if ($sent === false) {
                throw new AMQPIOException ("Error sending data. Last SocketError: "
                    . socket_strerror(socket_last_error()));
            }

            // Check if the entire message has been sent
            if ($sent < $len) {
                // If not sent the entire message.
                // Get the part of the message that has not yet been sent as message
                $data = mb_substr($data, $sent, mb_strlen($data, 'ASCII') - $sent, 'ASCII');
                // Get the length of the not sent part
                $len -= $sent;
            } else {
                break;
            }
        }
    }



    public function close()
    {
        if (is_resource($this->sock)) {
            socket_close($this->sock);
        }
        $this->sock = null;
    }



    public function select($sec, $usec)
    {
        $read = array($this->sock);
        $write = null;
        $except = null;
        return socket_select($read, $write, $except, $sec, $usec);
    }

    protected function enable_keepalive()
    {
        if (!defined('SOL_SOCKET') || !defined('SO_KEEPALIVE')) {
            throw new AMQPIOException("Can not enable keepalive: SOL_SOCKET or SO_KEEPALIVE is not defined");
        }

        socket_set_option($this->sock, SOL_SOCKET, SO_KEEPALIVE, 1);
    }

}

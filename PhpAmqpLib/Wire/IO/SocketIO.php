<?php

namespace PhpAmqpLib\Wire\IO;

use PhpAmqpLib\Exception\AMQPIOException;

class SocketIO extends AbstractIO
{
    private $sock = null;

    public function __construct($host, $port, $timeout)
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
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
        while ($read < $n && $buf !== '') {
            $read += strlen($buf);
            $res .= $buf;
            $buf = socket_read($this->sock, $n - $read);
        }

        if (strlen($res)!=$n) {
            throw new AMQPIOException("Error reading data. Received " .
                strlen($res) . " instead of expected $n bytes");
        }

        return $res;
    }

    public function write($data)
    {
        $len = strlen($data);

        while (true) {
            $sent = socket_write($this->sock, $data, $len);
            if ($sent === false) {
                throw new AMQPIOException("Error sending data");
            }
            // Check if the entire message has been sented
            if ($sent < $len) {
                // If not sent the entire message.
                // Get the part of the message that has not yet been sented as message
                $data = substr($data, $sent);
                // Get the length of the not sented part
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
        $read   = array($this->sock);
        $write  = null;
        $except = null;
        return socket_select($read, $write, $except, $sec, $usec);
    }
}
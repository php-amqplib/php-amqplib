<?php

namespace PhpAmqpLib\Wire\IO;

use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPRuntimeException;

class SocketIO extends AbstractIO
{
    private $sock = null;

    // Read timeout in secs
    protected $ioTimeout;

    public function __construct($host, $port, $timeout, $ioTimeout = 90)
    {

        $this->ioTimeout = $ioTimeout;

        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        socket_set_option($this->sock, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $timeout, 'usec' => 0));
        socket_set_option($this->sock, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $timeout, 'usec' => 0));

        if (!socket_connect($this->sock, $host, $port)) {
            $errno = socket_last_error($this->sock);
            $errstr = socket_strerror($errno);
            throw new AMQPIOException("Error Connecting to server($errno): $errstr ", $errno);
        }

        socket_set_block($this->sock);
        socket_set_option($this->sock, SOL_TCP, TCP_NODELAY, 1);
    }

    public function read($n)
    {
        $res = '';
        $read = 0;

        // ensure we don't get stuck in an infinite loop
        $startTime = time();

        $buf = socket_read($this->sock, $n);
        while ($read < $n && $buf !== '') {
            $read += strlen($buf);
            $res .= $buf;
            $buf = socket_read($this->sock, $n - $read);

            // check if ioTimeout has been exceded
            if ($read < $n && time() - $startTime > $this->ioTimeout) {
                throw new AMQPIOException("Error reading data. Received " .
                    strlen($res) . " instead of expected $n bytes - Timed out after ".$this->ioTimeout." secs of looping");
           }

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

        // ensure we don't get stuck in an infinite loop
        $startTime = time();

        while (true) {
            // Null sockets are invalid, throw exception
            if (is_null($this->sock)) {
                throw new AMQPRuntimeException("Socket was null! Last SocketError was: ".socket_strerror(socket_last_error()));
            }

            $sent = socket_write($this->sock, $data, $len);
            if ($sent === false) {
                $errorNo = socket_last_error($this->sock);
                throw new AMQPIOException ("Error sending data. Last SocketError: ".socket_strerror($errorNo));
            }
            // Check if the entire message has been sent
            if ($sent < $len) {
                // If not sent the entire message.
                // Get the part of the message that has not yet been sent as message
                $data = substr($data, $sent);
                // Get the length of the not sent part
                $len -= $sent;

                // check if ioTimeout has been exceded
                if (time() - $startTime > $this->ioTimeout) {
                    throw new AMQPIOException("Error writing data. Sent " .
                        $len . " instead of expected ".strlen($data)." bytes - Timed out after ".$this->ioTimeout." secs of looping");
                }
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

<?php
namespace PhpAmqpLib\Wire\IO;

use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPSocketException;
use PhpAmqpLib\Helper\MiscHelper;
use PhpAmqpLib\Wire\AMQPWriter;

class SocketIO extends AbstractIO
{
    /** @var string */
    protected $host;

    /** @var int */
    protected $port;

    /** @var float */
    protected $send_timeout;

    /** @var float */
    protected $read_timeout;

    /** @var int */
    protected $heartbeat;

    /** @var float */
    protected $last_read;

    /** @var float */
    protected $last_write;

    /** @var resource */
    private $sock;

    /** @var bool */
    private $keepalive;

    /**
     * @param string $host
     * @param int $port
     * @param float $read_timeout
     * @param bool $keepalive
     * @param float|null $write_timeout if null defaults to read timeout
     * @param int $heartbeat how often to send heartbeat. 0 means off
     */
    public function __construct($host, $port, $read_timeout, $keepalive = false, $write_timeout = null, $heartbeat = 0)
    {
        $this->host = $host;
        $this->port = $port;
        $this->read_timeout = $read_timeout;
        $this->send_timeout = $write_timeout ?: $read_timeout;
        $this->heartbeat = $heartbeat;
        $this->keepalive = $keepalive;
    }

    /**
     * Sets up the socket connection
     *
     * @throws \Exception
     */
    public function connect()
    {
        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        list($sec, $uSec) = MiscHelper::splitSecondsMicroseconds($this->send_timeout);
        socket_set_option($this->sock, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $sec, 'usec' => $uSec));
        list($sec, $uSec) = MiscHelper::splitSecondsMicroseconds($this->read_timeout);
        socket_set_option($this->sock, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $sec, 'usec' => $uSec));

        if (!socket_connect($this->sock, $this->host, $this->port)) {
            $errno = socket_last_error($this->sock);
            $errstr = socket_strerror($errno);
            throw new AMQPIOException(sprintf(
                'Error Connecting to server (%s): %s',
                $errno,
                $errstr
            ), $errno);
        }

        socket_set_block($this->sock);
        socket_set_option($this->sock, SOL_TCP, TCP_NODELAY, 1);

        if ($this->keepalive) {
            $this->enable_keepalive();
        }
    }

    /**
     * @return resource
     */
    public function getSocket()
    {
        return $this->sock;
    }

    /**
     * Reconnects the socket
     */
    public function reconnect()
    {
        $this->close();
        $this->connect();
    }

    /**
     * @param int $n
     * @return mixed|string
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     * @throws \PhpAmqpLib\Exception\AMQPSocketException
     */
    public function read($n)
    {
        if (is_null($this->sock)) {
            throw new AMQPSocketException(sprintf(
                'Socket was null! Last SocketError was: %s',
                socket_strerror(socket_last_error())
            ));
        }
        $res = '';
        $read = 0;
        $buf = socket_read($this->sock, $n);
        while ($read < $n && $buf !== '' && $buf !== false) {
            $this->check_heartbeat();

            $read += mb_strlen($buf, 'ASCII');
            $res .= $buf;
            $buf = socket_read($this->sock, $n - $read);
        }

        if (mb_strlen($res, 'ASCII') != $n) {
            throw new AMQPIOException(sprintf(
                'Error reading data. Received %s instead of expected %s bytes',
                mb_strlen($res, 'ASCII'),
                $n
            ));
        }

        $this->last_read = microtime(true);

        return $res;
    }

    /**
     * @param string $data
     * @return void
     *
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     * @throws \PhpAmqpLib\Exception\AMQPSocketException
     */
    public function write($data)
    {
        $len = mb_strlen($data, 'ASCII');

        while (true) {
            // Null sockets are invalid, throw exception
            if (is_null($this->sock)) {
                throw new AMQPSocketException(sprintf(
                    'Socket was null! Last SocketError was: %s',
                    socket_strerror(socket_last_error())
                ));
            }

            $sent = socket_write($this->sock, $data, $len);
            if ($sent === false) {
                throw new AMQPIOException(sprintf(
                    'Error sending data. Last SocketError: %s',
                    socket_strerror(socket_last_error())
                ));
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

        $this->last_write = microtime(true);
    }

    public function close()
    {
        if (is_resource($this->sock)) {
            socket_close($this->sock);
        }
        $this->sock = null;
        $this->last_read = null;
        $this->last_write = null;
    }

    /**
     * @param int $sec
     * @param int $usec
     * @return int|mixed
     */
    public function select($sec, $usec)
    {
        $read = array($this->sock);
        $write = null;
        $except = null;

        return socket_select($read, $write, $except, $sec, $usec);
    }

    /**
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     */
    protected function enable_keepalive()
    {
        if (!defined('SOL_SOCKET') || !defined('SO_KEEPALIVE')) {
            throw new AMQPIOException('Can not enable keepalive: SOL_SOCKET or SO_KEEPALIVE is not defined');
        }

        socket_set_option($this->sock, SOL_SOCKET, SO_KEEPALIVE, 1);
    }

    /**
     * Heartbeat logic: check connection health here
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function check_heartbeat()
    {
        // ignore unless heartbeat interval is set
        if ($this->heartbeat !== 0 && $this->last_read && $this->last_write) {
            $t = microtime(true);
            $t_read = round($t - $this->last_read);
            $t_write = round($t - $this->last_write);

            // server has gone away
            if (($this->heartbeat * 2) < $t_read) {
                $this->close();
                throw new AMQPHeartbeatMissedException("Missed server heartbeat");
            }

            // time for client to send a heartbeat
            if (($this->heartbeat / 2) < $t_write) {
                $this->write_heartbeat();
            }
        }
    }

    /**
     * Sends a heartbeat message
     */
    protected function write_heartbeat()
    {
        $pkt = new AMQPWriter();
        $pkt->write_octet(8);
        $pkt->write_short(0);
        $pkt->write_long(0);
        $pkt->write_octet(0xCE);
        $this->write($pkt->getvalue());
    }
}

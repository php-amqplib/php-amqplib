<?php
namespace PhpAmqpLib\Wire\IO;

use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPDataReadException;
use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Helper\MiscHelper;
use PhpAmqpLib\Wire\AMQPWriter;

class StreamIO extends AbstractIO
{
    /** @var string */
    protected $protocol;

    /** @var string */
    protected $host;

    /** @var int */
    protected $port;

    /** @var float */
    protected $connection_timeout;

    /** @var float */
    protected $read_write_timeout;

    /** @var resource */
    protected $context;

    /** @var bool */
    protected $keepalive;

    /** @var int */
    protected $heartbeat;

    /** @var float */
    protected $last_read;

    /** @var float */
    protected $last_write;

    /** @var array */
    protected $last_error;

    /** @var int */
    private $initial_heartbeat;

    /** @var resource */
    private $sock;

    /** @var bool */
    private $canDispatchPcntlSignal;

    /**
     * @param string $host
     * @param int $port
     * @param float $connection_timeout
     * @param float $read_write_timeout
     * @param null $context
     * @param bool $keepalive
     * @param int $heartbeat
     */
    public function __construct(
        $host,
        $port,
        $connection_timeout,
        $read_write_timeout,
        $context = null,
        $keepalive = false,
        $heartbeat = 0
    ) {
        if ($heartbeat !== 0 && ($read_write_timeout < ($heartbeat * 2))) {
            throw new \InvalidArgumentException('read_write_timeout must be at least 2x the heartbeat');
        }

        $this->protocol = 'tcp';
        $this->host = $host;
        $this->port = $port;
        $this->connection_timeout = $connection_timeout;
        $this->read_write_timeout = $read_write_timeout;
        $this->context = $context;
        $this->keepalive = $keepalive;
        $this->heartbeat = $heartbeat;
        $this->initial_heartbeat = $heartbeat;
        $this->canDispatchPcntlSignal = $this->isPcntlSignalEnabled();

        if (is_null($this->context)) {
            // tcp_nodelay was added in 7.1.0
            if (PHP_VERSION_ID >= 70100) {
                $this->context = stream_context_create(array(
                    "socket" => array(
                        "tcp_nodelay" => true
                    )
                ));
            } else {
                $this->context = stream_context_create();
            }
        } else {
            $this->protocol = 'ssl';
        }
    }

    /**
     * @return bool
     */
    private function isPcntlSignalEnabled()
    {
        return extension_loaded('pcntl')
            && function_exists('pcntl_signal_dispatch')
            && (defined('AMQP_WITHOUT_SIGNALS') ? !AMQP_WITHOUT_SIGNALS : true);
    }

    /**
     * Sets up the stream connection
     *
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     * @throws \Exception
     */
    public function connect()
    {
        $errstr = $errno = null;

        $remote = sprintf(
            '%s://%s:%s',
            $this->protocol,
            $this->host,
            $this->port
        );

        $this->set_error_handler();

        try {
            $this->sock = stream_socket_client(
                $remote,
                $errno,
                $errstr,
                $this->connection_timeout,
                STREAM_CLIENT_CONNECT,
                $this->context
            );
            $this->cleanup_error_handler();
        } catch (\ErrorException $e) {
            restore_error_handler();
            throw $e;
        }

        restore_error_handler();

        if (false === $this->sock) {
            throw new AMQPRuntimeException(
                sprintf(
                    'Error Connecting to server(%s): %s ',
                    $errno,
                    $errstr
                ),
                $errno
            );
        }

        if (false === stream_socket_get_name($this->sock, true)) {
            throw new AMQPRuntimeException(
                sprintf(
                    'Connection refused: %s ',
                    $remote
                )
            );
        }

        list($sec, $uSec) = MiscHelper::splitSecondsMicroseconds($this->read_write_timeout);
        if (!stream_set_timeout($this->sock, $sec, $uSec)) {
            throw new AMQPIOException('Timeout could not be set');
        }

        // php cannot capture signals while streams are blocking
        if ($this->canDispatchPcntlSignal) {
            stream_set_blocking($this->sock, 0);
            stream_set_write_buffer($this->sock, 0);
            if (function_exists('stream_set_read_buffer')) {
                stream_set_read_buffer($this->sock, 0);
            }
        } else {
            stream_set_blocking($this->sock, 1);
        }

        if ($this->keepalive) {
            $this->enable_keepalive();
        }
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
     * @param int $len
     * @throws \ErrorException
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     * @throws \PhpAmqpLib\Exception\AMQPDataReadException
     * @return mixed|string
     */
    public function read($len)
    {
        $this->check_heartbeat();

        list($timeout_sec, $timeout_uSec) =
            MiscHelper::splitSecondsMicroseconds($this->read_write_timeout);

        $read_start = microtime(true);
        $read = 0;
        $data = '';

        while ($read < $len) {
            if (!is_resource($this->sock) || feof($this->sock)) {
                throw new AMQPConnectionClosedException('Broken pipe or closed connection');
            }

            $this->set_error_handler();
            try {
                $buffer = fread($this->sock, ($len - $read));
                $this->cleanup_error_handler();
            } catch (\ErrorException $e) {
                restore_error_handler();
                throw $e;
            }

            if ($buffer === false) {
                throw new AMQPDataReadException('Error receiving data');
            }

            if ($buffer === '') {
                $read_now = microtime(true);
                $t_read = round($read_now - $read_start);
                if ($t_read > $this->read_write_timeout) {
                    throw new AMQPTimeoutException('Too many read attempts detected in StreamIO');
                }
                $this->select($timeout_sec, $timeout_uSec);
                if ($this->canDispatchPcntlSignal) {
                    pcntl_signal_dispatch();
                }
                $this->check_heartbeat();
                continue;
            }

            $this->last_read = microtime(true);
            $read_start = $this->last_read;
            $read += mb_strlen($buffer, 'ASCII');
            $data .= $buffer;
        }

        if (mb_strlen($data, 'ASCII') !== $len) {
            throw new AMQPDataReadException(
                sprintf(
                    'Error reading data. Received %s instead of expected %s bytes',
                    mb_strlen($data, 'ASCII'),
                    $len
                )
            );
        }

        return $data;
    }

    /**
     * @param string $data
     * @return mixed|void
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     */
    public function write($data)
    {
        $written = 0;
        $len = mb_strlen($data, 'ASCII');

        while ($written < $len) {

            if (!is_resource($this->sock)) {
                throw new AMQPConnectionClosedException('Broken pipe or closed connection');
            }

            $this->set_error_handler();
            // OpenSSL's C library function SSL_write() can balk on buffers > 8192
            // bytes in length, so we're limiting the write size here. On both TLS
            // and plaintext connections, the write loop will continue until the
            // buffer has been fully written.
            // This behavior has been observed in OpenSSL dating back to at least
            // September 2002:
            // http://comments.gmane.org/gmane.comp.encryption.openssl.user/4361
            try {
                $buffer = fwrite($this->sock, mb_substr($data, $written, 8192, 'ASCII'), 8192);
                $this->cleanup_error_handler();
            } catch (\ErrorException $e) {
                restore_error_handler();
                throw new AMQPRuntimeException($e->getMessage());
            }
            restore_error_handler();

            if ($buffer === false) {
                throw new AMQPRuntimeException('Error sending data');
            }

            if ($buffer === 0 && feof($this->sock)) {
                throw new AMQPConnectionClosedException('Broken pipe or closed connection');
            }

            if ($this->timed_out()) {
                throw new AMQPTimeoutException('Error sending data. Socket connection timed out');
            }

            $written += $buffer;
        }

        $this->last_write = microtime(true);
    }

    /**
     * Internal error handler to deal with stream and socket errors that need to be ignored
     *
     * @param  int $errno
     * @param  string $errstr
     * @param  string $errfile
     * @param  int $errline
     * @param  array $errcontext
     * @return null
     * @throws \ErrorException
     */
    public function error_handler($errno, $errstr, $errfile, $errline, $errcontext = null)
    {
        // fwrite notice that the stream isn't ready - EAGAIN or EWOULDBLOCK
        if ($errno == SOCKET_EAGAIN || $errno == SOCKET_EWOULDBLOCK) {
             // it's allowed to retry
            return null;
        }

        // stream_select warning that it has been interrupted by a signal - EINTR
        if ($errno == SOCKET_EINTR) {
             // it's allowed while processing signals
            return null;
        }

        // throwing an exception in an error handler will halt execution
        //   set the last error and continue
        $this->last_error = compact('errno', 'errstr', 'errfile', 'errline', 'errcontext');
    }

    /**
     * Begin tracking errors and set the error handler
     */
    protected function set_error_handler()
    {
        $this->last_error = null;
        set_error_handler(array($this, 'error_handler'));
    }

    /**
     * throws an ErrorException if an error was handled
     */
    protected function cleanup_error_handler()
    {
        if ($this->last_error !== null) {
            throw new \ErrorException($this->last_error['errstr'], 0, $this->last_error['errno'], $this->last_error['errfile'], $this->last_error['errline']);
        }

        // no error was caught
        restore_error_handler();
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

    public function close()
    {
        if (is_resource($this->sock)) {
            fclose($this->sock);
        }
        $this->sock = null;
        $this->last_read = null;
        $this->last_write = null;
    }

    /**
     * @return resource
     */
    public function get_socket()
    {
        return $this->sock;
    }

    /**
     * @return resource
     */
    public function getSocket()
    {
        return $this->get_socket();
    }

    /**
     * @param int $sec
     * @param int $usec
     * @return int|mixed
     * @throws \ErrorException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function select($sec, $usec)
    {
        $this->check_heartbeat();

        $read = array($this->sock);
        $write = null;
        $except = null;
        $result = false;

        if (defined('HHVM_VERSION')) {
            $usec = is_int($usec) ? $usec : 0;
        }

        $this->set_error_handler();
        try {
            $result = stream_select($read, $write, $except, $sec, $usec);
            $this->cleanup_error_handler();
        } catch (\ErrorException $e) {
            restore_error_handler();
            throw $e;
        }
        restore_error_handler();

        return $result;
    }

    /**
     * @return mixed
     */
    protected function timed_out()
    {
        // get status of socket to determine whether or not it has timed out
        $info = stream_get_meta_data($this->sock);

        return $info['timed_out'];
    }

    /**
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     */
    protected function enable_keepalive()
    {
        if (!function_exists('socket_import_stream')) {
            throw new AMQPIOException('Can not enable keepalive: function socket_import_stream does not exist');
        }

        if (!defined('SOL_SOCKET') || !defined('SO_KEEPALIVE')) {
            throw new AMQPIOException('Can not enable keepalive: SOL_SOCKET or SO_KEEPALIVE is not defined');
        }

        $socket = socket_import_stream($this->sock);
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
    }

    /**
     * @return $this
     */
    public function disableHeartbeat()
    {
        $this->heartbeat = 0;

        return $this;
    }

    /**
     * @return $this
     */
    public function reenableHeartbeat()
    {
        $this->heartbeat = $this->initial_heartbeat;

        return $this;
    }
}

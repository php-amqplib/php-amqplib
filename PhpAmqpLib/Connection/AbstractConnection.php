<?php
namespace PhpAmqpLib\Connection;

use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPProtocolConnectionException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Helper\MiscHelper;
use PhpAmqpLib\Wire\AMQPReader;
use PhpAmqpLib\Wire\AMQPWriter;
use PhpAmqpLib\Wire\IO\AbstractIO;
use PhpAmqpLib\Wire\IO\SocketIO;

class AbstractConnection extends AbstractChannel
{
    /** @var array */
    public static $LIBRARY_PROPERTIES = array(
        'library' => array('S', 'PHP AMQP Lib'),
        'library_version' => array('S', '2.0'),
        'capabilities' => array(
            'F',
            array(
                'publisher_confirms' => array('t', true),
                'consumer_cancel_notify' => array('t', true),
                'exchange_exchange_bindings' => array('t', true),
                'basic.nack' => array('t', true),
                'connection.blocked' => array('t', true)
            )
        )
    );

    /** @var AMQPChannel[] */
    public $channels = array();

    /** @var int */
    protected $version_major;

    /** @var int */
    protected $version_minor;

    /** @var array */
    protected $server_properties;

    /** @var string */
    protected $heartbeat;

    /** @var array */
    protected $mechanisms;

    /** @var array */
    protected $locales;

    /** @var bool */
    protected $wait_tune_ok;

    /** @var string */
    protected $known_hosts;

    /** @var AMQPReader */
    protected $input;

    /** @var string */
    protected $vhost;

    /** @var bool */
    protected $insist;

    /** @var string */
    protected $login_method;

    /** @var AMQPWriter */
    protected $login_response;

    /** @var string */
    protected $locale;

    /** @var SocketIO */
    protected $sock;

    /** @var int */
    protected $channel_max = 65535;

    /** @var int */
    protected $frame_max = 131072;

     /** @var array Constructor parameters for clone */
    protected $construct_params;

    /** @var bool Close the connection in destructor */
    protected $close_on_destruct = true;

    /** @var bool Maintain connection status */
    protected $is_connected = false;

    /** @var \PhpAmqpLib\Wire\IO\AbstractIO */
    protected $io;

    /** @var \PhpAmqpLib\Wire\AMQPReader */
    protected $wait_frame_reader;

    /** @var callable Handles connection blocking from the server */
    private $connection_block_handler;

    /** @var callable Handles connection unblocking from the server */
    private $connection_unblock_handler;

    /**
     * Circular buffer to speed up prepare_content().
     * Max size limited by $prepare_content_cache_max_size.
     *
     * @var array
     * @see prepare_content()
     */
    private $prepare_content_cache;

    /** @var int Maximal size of $prepare_content_cache */
    private $prepare_content_cache_max_size;

    /**
     * @param AbstractConnection $user
     * @param string $password
     * @param string $vhost
     * @param bool $insist
     * @param string $login_method
     * @param null $login_response
     * @param string $locale
     * @param AbstractIO $io
     */
    public function __construct(
        $user,
        $password,
        $vhost = '/',
        $insist = false,
        $login_method = 'AMQPLAIN',
        $login_response = null,
        $locale = 'en_US',
        AbstractIO $io
    ) {
        // save the params for the use of __clone
        $this->construct_params = func_get_args();

        $this->wait_frame_reader = new AMQPReader(null);
        $this->vhost = $vhost;
        $this->insist = $insist;
        $this->login_method = $login_method;
        $this->login_response = $login_response;
        $this->locale = $locale;
        $this->io = $io;

        if ($user && $password) {
            $this->login_response = new AMQPWriter();
            $this->login_response->write_table(array(
                'LOGIN' => array('S', $user),
                'PASSWORD' => array('S', $password)
            ));

            // Skip the length
            $responseValue = $this->login_response->getvalue();
            $this->login_response = mb_substr($responseValue, 4, mb_strlen($responseValue, 'ASCII') - 4, 'ASCII');

        } else {
            $this->login_response = null;
        }

        $this->prepare_content_cache = array();
        $this->prepare_content_cache_max_size = 100;

        // Lazy Connection waits on connecting
        if ($this->connectOnConstruct()) {
            $this->connect();
        }
    }

    /**
     * Connectes to the AMQP server
     */
    protected function connect()
    {
        try {
            // Loop until we connect
            while (!$this->isConnected()) {
                // Assume we will connect, until we dont
                $this->setIsConnected(true);

                // Connect the socket
                $this->getIO()->connect();

                $this->channels = array();
                // The connection object itself is treated as channel 0
                parent::__construct($this, 0);

                $this->input = new AMQPReader(null, $this->getIO());

                $this->write($this->amqp_protocol_header);
                $this->wait(array($this->waitHelper->get_wait('connection.start')));
                $this->x_start_ok(self::$LIBRARY_PROPERTIES, $this->login_method, $this->login_response, $this->locale);

                $this->wait_tune_ok = true;
                while ($this->wait_tune_ok) {
                    $this->wait(array(
                        $this->waitHelper->get_wait('connection.secure'),
                        $this->waitHelper->get_wait('connection.tune')
                    ));
                }

                $host = $this->x_open($this->vhost, '', $this->insist);
                if (!$host) {
                    return; // we weren't redirected
                }

                $this->setIsConnected(false);
                $this->closeChannels();

                // we were redirected, close the socket, loop and try again
                $this->close_socket();
            }

        } catch (\Exception $e) {
            // Something went wrong, set the connection status
            $this->setIsConnected(false);
            $this->closeChannels();
            throw $e; // Rethrow exception
        }
    }

    /**
     * Reconnects using the original connection settings.
     * This will not recreate any channels that were established previously
     */
    public function reconnect()
    {
        try {
            // Try to close the AMQP connection
            $this->safeClose();
        } catch (\Exception $e) {
            // Ignore closing errors
        }

        // Reconnect the socket/stream then AMQP
        $this->getIO()->reconnect();
        $this->setIsConnected(false); // getIO can initiate the connection setting via LazyConnection, set it here to be sure
        $this->connect();
    }

    /**
     * Cloning will use the old properties to make a new connection to the same server
     */
    public function __clone()
    {
        call_user_func_array(array($this, '__construct'), $this->construct_params);
    }

    public function __destruct()
    {
        if ($this->close_on_destruct) {
            $this->safeClose();
        }
    }

    /**
     * Attempts to close the connection safely
     */
    protected function safeClose()
    {
        // Set the connection status in case the server has gone away
        $this->setIsConnected(false);
        $this->closeChannels();

        if (isset($this->input) && $this->input) {
            // close() always tries to connect to the server to shutdown
            // the connection. If the server has gone away, it will
            // throw an error in the connection class, so catch it
            // and shutdown quietly
            try {
                $this->close();
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * @param int $sec
     * @param int $usec
     * @return mixed
     */
    public function select($sec, $usec = 0)
    {
        return $this->getIO()->select($sec, $usec);
    }

    /**
     * Allows to not close the connection
     * it's useful after the fork when you don't want to close parent process connection
     *
     * @param bool $close
     */
    public function set_close_on_destruct($close = true)
    {
        $this->close_on_destruct = (bool) $close;
    }

    protected function close_socket()
    {
        if ($this->debug) {
            MiscHelper::debug_msg('closing socket');
        }

        $this->getIO()->close();
    }

    /**
     * @param $data
     */
    public function write($data)
    {
        if ($this->debug) {
            MiscHelper::debug_msg(sprintf(
                '< [hex]:%s%s',
                PHP_EOL,
                MiscHelper::hexdump($data, $htmloutput = false, $uppercase = true, $return = true)
            ));
        }

        $this->getIO()->write($data);
    }

    protected function do_close()
    {
        if (isset($this->input) && $this->input) {
            $this->input->close();
            $this->input = null;
        }

        $this->close_socket();
    }

    /**
     * @return int
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function get_free_channel_id()
    {
        for ($i = 1; $i <= $this->channel_max; $i++) {
            if (!isset($this->channels[$i])) {
                return $i;
            }
        }

        throw new AMQPRuntimeException('No free channel ids');
    }

    /**
     * @param string $channel
     * @param int $class_id
     * @param int $weight
     * @param int $body_size
     * @param string $packed_properties
     * @param string $body
     * @param AMQPWriter $pkt
     */
    public function send_content($channel, $class_id, $weight, $body_size, $packed_properties, $body, AMQPWriter $pkt = null)
    {
        $this->prepare_content($channel, $class_id, $weight, $body_size, $packed_properties, $body, $pkt);
        $this->write($pkt->getvalue());
    }

    /**
     * Returns a new AMQPWriter or mutates the provided $pkt
     *
     * @param string $channel
     * @param int $class_id
     * @param int $weight
     * @param int $body_size
     * @param string $packed_properties
     * @param string $body
     * @param AMQPWriter $pkt
     * @return AMQPWriter
     */
    public function prepare_content($channel, $class_id, $weight, $body_size, $packed_properties, $body, AMQPWriter $pkt = null)
    {
        $pkt = $pkt ?: new AMQPWriter();

        // Content already prepared ?
        $key_cache = sprintf(
            '%s|%s|%s|%s',
            $channel,
            $packed_properties,
            $class_id,
            $weight
        );

        if (!isset($this->prepare_content_cache[$key_cache])) {
            $w = new AMQPWriter();
            $w->write_octet(2);
            $w->write_short($channel);
            $w->write_long(mb_strlen($packed_properties, 'ASCII') + 12);
            $w->write_short($class_id);
            $w->write_short($weight);
            $this->prepare_content_cache[$key_cache] = $w->getvalue();
            if (count($this->prepare_content_cache) > $this->prepare_content_cache_max_size) {
                reset($this->prepare_content_cache);
                $old_key = key($this->prepare_content_cache);
                unset($this->prepare_content_cache[$old_key]);
            }
        }
        $pkt->write($this->prepare_content_cache[$key_cache]);

        $pkt->write_longlong($body_size);
        $pkt->write($packed_properties);

        $pkt->write_octet(0xCE);

        while ($body) {
            $bodyStart = ($this->frame_max - 8);
            $payload = mb_substr($body, 0, $bodyStart, 'ASCII');
            $body = mb_substr($body, $bodyStart, mb_strlen($body, 'ASCII') - $bodyStart, 'ASCII');

            $pkt->write_octet(3);
            $pkt->write_short($channel);
            $pkt->write_long(mb_strlen($payload, 'ASCII'));

            $pkt->write($payload);

            $pkt->write_octet(0xCE);
        }

        return $pkt;
    }

    /**
     * @param $channel
     * @param $method_sig
     * @param string $args
     * @param null $pkt
     */
    protected function send_channel_method_frame($channel, $method_sig, $args = '', $pkt = null)
    {
        $pkt = $this->prepare_channel_method_frame($channel, $method_sig, $args, $pkt);

        $this->write($pkt->getvalue());

        if ($this->debug) {
            $protocol = self::$PROTOCOL_CONSTANTS_CLASS;
            MiscHelper::debug_msg(sprintf(
                '< %s: %s',
                MiscHelper::methodSig($method_sig),
                $protocol::$GLOBAL_METHOD_NAMES[MiscHelper::methodSig($method_sig)]
            ));
        }
    }

    /**
     * Returns a new AMQPWriter or mutates the provided $pkt
     *
     * @param $channel
     * @param $method_sig
     * @param string $args
     * @param AMQPWriter $pkt
     * @return null|AMQPWriter
     */
    protected function prepare_channel_method_frame($channel, $method_sig, $args = '', AMQPWriter $pkt = null)
    {
        if ($args instanceof AMQPWriter) {
            $args = $args->getvalue();
        }

        $pkt = $pkt ?: new AMQPWriter();

        $pkt->write_octet(1);
        $pkt->write_short($channel);
        $pkt->write_long(mb_strlen($args, 'ASCII') + 4); // 4 = length of class_id and method_id
        // in payload

        $pkt->write_short($method_sig[0]); // class_id
        $pkt->write_short($method_sig[1]); // method_id
        $pkt->write($args);

        $pkt->write_octet(0xCE);

        if ($this->debug) {
            $protocol = self::$PROTOCOL_CONSTANTS_CLASS;
            MiscHelper::debug_msg(sprintf(
                '< %s: %s',
                MiscHelper::methodSig($method_sig),
                $protocol::$GLOBAL_METHOD_NAMES[MiscHelper::methodSig($method_sig)]
            ));
        }

        return $pkt;
    }

    /**
     * Waits for a frame from the server
     *
     * @param int $timeout
     * @return array
     * @throws \Exception
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    protected function wait_frame($timeout = 0)
    {
        $currentTimeout = $this->input->getTimeout();
        $this->input->setTimeout($timeout);

        try {
            // frame_type + channel_id + size
            $this->wait_frame_reader->reuse(
                $this->input->read(AMQPReader::OCTET + AMQPReader::SHORT + AMQPReader::LONG)
            );

            $frame_type = $this->wait_frame_reader->read_octet();
            $channel = $this->wait_frame_reader->read_short();
            $size = $this->wait_frame_reader->read_long();

            // payload + ch
            $this->wait_frame_reader->reuse($this->input->read(AMQPReader::OCTET + (int) $size));

            $payload = $this->wait_frame_reader->read($size);
            $ch = $this->wait_frame_reader->read_octet();

        } catch (AMQPTimeoutException $e) {
            $this->input->setTimeout($currentTimeout);
            throw $e;
        }

        $this->input->setTimeout($currentTimeout);

        if ($ch != 0xCE) {
            throw new AMQPRuntimeException(sprintf(
                'Framing error, unexpected byte: %x',
                $ch
            ));
        }

        return array($frame_type, $channel, $payload);
    }



    /**
     * Waits for a frame from the server destined for a particular channel.
     *
     * @param string $channel_id
     * @param int $timeout
     * @return array
     */
    protected function wait_channel($channel_id, $timeout = 0)
    {
        while (true) {
            list($frame_type, $frame_channel, $payload) = $this->wait_frame($timeout);
            if ($frame_channel == $channel_id) {
                return array($frame_type, $payload);
            }

            // Not the channel we were looking for.  Queue this frame
            //for later, when the other channel is looking for frames.
            array_push($this->channels[$frame_channel]->frame_queue, array($frame_type, $payload));

            // If we just queued up a method for channel 0 (the Connection
            // itself) it's probably a close method in reaction to some
            // error, so deal with it right away.
            if (($frame_type == 1) && ($frame_channel == 0)) {
                $this->wait();
            }
        }
    }

    /**
    /**
     * Fetches a channel object identified by the numeric channel_id, or
     * create that object if it doesn't already exist.
     *
     * @param string $channel_id
     * @return AMQPChannel
     */
    public function channel($channel_id = null)
    {
        if (isset($this->channels[$channel_id])) {
            return $this->channels[$channel_id];
        }

        $channel_id = $channel_id ? $channel_id : $this->get_free_channel_id();
        $ch = new AMQPChannel($this->connection, $channel_id);
        $this->channels[$channel_id] = $ch;

        return $ch;
    }

    /**
     * Requestes a connection close
     *
     * @param int $reply_code
     * @param string $reply_text
     * @param array $method_sig
     * @return mixed|null
     */
    public function close($reply_code = 0, $reply_text = '', $method_sig = array(0, 0))
    {
        if (!$this->protocolWriter || !$this->isConnected()) {
            return null;
        }

        $this->closeChannels();

        list($class_id, $method_id, $args) = $this->protocolWriter->connectionClose(
            $reply_code,
            $reply_text,
            $method_sig[0],
            $method_sig[1]
        );
        $this->send_method_frame(array($class_id, $method_id), $args);

        $this->setIsConnected(false);

        return $this->wait(array(
            $this->waitHelper->get_wait('connection.close_ok')
        ));
    }

    /**
     * @param $table
     * @return string
     */
    public static function dump_table($table)
    {
        $tokens = array();
        foreach ($table as $name => $value) {
            switch ($value[0]) {
                case 'D':
                    $val = $value[1]->n . 'E' . $value[1]->e;
                    break;
                case 'F':
                    $val = '(' . self::dump_table($value[1]) . ')';
                    break;
                case 'T':
                    $val = date('Y-m-d H:i:s', $value[1]);
                    break;
                default:
                    $val = $value[1];
            }
            $tokens[] = $name . '=' . $val;
        }

        return implode(', ', $tokens);

    }

    /**
     * @param AMQPReader $args
     * @throws \PhpAmqpLib\Exception\AMQPProtocolConnectionException
     */
    protected function connection_close(AMQPReader $args)
    {
        $reply_code = $args->read_short();
        $reply_text = $args->read_shortstr();
        $class_id = $args->read_short();
        $method_id = $args->read_short();

        $this->x_close_ok();

        throw new AMQPProtocolConnectionException($reply_code, $reply_text, array($class_id, $method_id));
    }

    /**
     * Confirms a connection close
     */
    protected function x_close_ok()
    {
        $this->send_method_frame(array(10, 61));
        $this->do_close();
    }

    /**
     * Confirm a connection close
     */
    protected function connection_close_ok()
    {
        $this->do_close();
    }

    /**
     * @param string $virtual_host
     * @param string $capabilities
     * @param bool $insist
     * @return mixed
     */
    protected function x_open($virtual_host, $capabilities = '', $insist = false)
    {
        $args = new AMQPWriter();
        $args->write_shortstr($virtual_host);
        $args->write_shortstr($capabilities);
        $args->write_bits(array($insist));
        $this->send_method_frame(array(10, 40), $args);

        $wait = array(
            $this->waitHelper->get_wait('connection.open_ok')
        );

        if ($this->protocolVersion == '0.8') {
            $wait[] = $this->waitHelper->get_wait('connection.redirect');
        }

        return $this->wait($wait);
    }

    /**
     * Signals that the connection is ready
     *
     * @param AMQPReader $args
     */
    protected function connection_open_ok(AMQPReader $args)
    {
        $this->known_hosts = $args->read_shortstr();
        if ($this->debug) {
            MiscHelper::debug_msg('Open OK! known_hosts: ' . $this->known_hosts);
        }
    }

    /**
     * Asks the client to use a different server
     *
     * @param AMQPReader $args
     * @return string
     */
    protected function connection_redirect(AMQPReader $args)
    {
        $host = $args->read_shortstr();
        $this->known_hosts = $args->read_shortstr();
        if ($this->debug) {
            MiscHelper::debug_msg(sprintf(
                'Redirected to [%s], known_hosts [%s]',
                $host,
                $this->known_hosts
            ));
        }

        return $host;
    }

    /**
     * Security mechanism challenge
     *
     * @param AMQPReader $args
     */
    protected function connection_secure(AMQPReader $args)
    {
        $args->read_longstr();
    }

    /**
     * Security mechanism response
     */
    protected function x_secure_ok($response)
    {
        $args = new AMQPWriter();
        $args->write_longstr($response);
        $this->send_method_frame(array(10, 21), $args);
    }

    /**
     * Starts connection negotiation
     *
     * @param AMQPReader $args
     */
    protected function connection_start(AMQPReader $args)
    {
        $this->version_major = $args->read_octet();
        $this->version_minor = $args->read_octet();
        $this->server_properties = $args->read_table();
        $this->mechanisms = explode(' ', $args->read_longstr());
        $this->locales = explode(' ', $args->read_longstr());

        if ($this->debug) {
            MiscHelper::debug_msg(sprintf(
                'Start from server, version: %d.%d, properties: %s, mechanisms: %s, locales: %s',
                $this->version_major,
                $this->version_minor,
                self::dump_table($this->server_properties),
                implode(', ', $this->mechanisms),
                implode(', ', $this->locales)
            ));
        }
    }

    /**
     * @param $client_properties
     * @param $mechanism
     * @param $response
     * @param $locale
     */
    protected function x_start_ok($client_properties, $mechanism, $response, $locale)
    {
        $args = new AMQPWriter();
        $args->write_table($client_properties);
        $args->write_shortstr($mechanism);
        $args->write_longstr($response);
        $args->write_shortstr($locale);
        $this->send_method_frame(array(10, 11), $args);
    }

    /**
     * Proposes connection tuning parameters
     *
     * @param AMQPReader $args
     */
    protected function connection_tune(AMQPReader $args)
    {
        $v = $args->read_short();
        if ($v) {
            $this->channel_max = $v;
        }

        $v = $args->read_long();
        if ($v) {
            $this->frame_max = $v;
        }

        $this->heartbeat = $args->read_short();
        $this->x_tune_ok($this->channel_max, $this->frame_max, 0);
    }

    /**
     * Negotiates connection tuning parameters
     *
     * @param $channel_max
     * @param $frame_max
     * @param $heartbeat
     */
    protected function x_tune_ok($channel_max, $frame_max, $heartbeat)
    {
        $args = new AMQPWriter();
        $args->write_short($channel_max);
        $args->write_long($frame_max);
        $args->write_short($heartbeat);
        $this->send_method_frame(array(10, 31), $args);
        $this->wait_tune_ok = false;
    }

    /**
     * @return SocketIO
     */
    public function getSocket()
    {
        return $this->sock;
    }

    /**
     * @return \PhpAmqpLib\Wire\IO\AbstractIO
     */
    protected function getIO()
    {
        return $this->io;
    }

    /**
     * Handles connection blocked notifications
     *
     * @param AMQPReader $args
     */
    protected function connection_blocked(AMQPReader $args)
    {
        // Call the block handler and pass in the reason
        $this->dispatch_to_handler($this->connection_block_handler, array($args->read_shortstr()));
    }

    /**
     * Handles connection unblocked notifications
     */
    protected function connection_unblocked()
    {
        // No args to an unblock event
        $this->dispatch_to_handler($this->connection_unblock_handler, array());
    }

    /**
     * Sets a handler which is called whenever a connection.block is sent from the server
     *
     * @param callable $callback
     */
    public function set_connection_block_handler($callback)
    {
        $this->connection_block_handler = $callback;
    }

    /**
     * Sets a handler which is called whenever a connection.block is sent from the server
     *
     * @param callable $callback
     */
    public function set_connection_unblock_handler($callback)
    {
        $this->connection_unblock_handler = $callback;
    }

    /**
     * Gets the connection status
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->is_connected;
    }

    /**
     * Set the connection status
     *
     * @param bool $is_connected
     */
    protected function setIsConnected($is_connected)
    {
        $this->is_connected = (bool) $is_connected;
    }

    protected function closeChannels()
    {
        foreach ($this->channels as $key => $channel) {
            // channels[0] is this connection object, so don't close it yet
            if ($key === 0) {
                continue;
            }
            try {
                $channel->close();
            } catch (\Exception $e) { /* Ignore closing errors */
            }
        }
    }

    /**
     * Should the connection be attempted during construction?
     *
     * @return bool
     */
    public function connectOnConstruct()
    {
        return true;
    }
}

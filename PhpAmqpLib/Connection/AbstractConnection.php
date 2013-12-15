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

class AbstractConnection extends AbstractChannel
{
    /**
     * Circular buffer to speed up prepare_content().
     * Max size limited by $prepare_content_cache_max_size.
     * @var array
     * @see prepare_content()
     */
    private $prepare_content_cache;

    /**
     * Maximal size of $prepare_content_cache.
     * @var int
     */
    private $prepare_content_cache_max_size;

    public static $LIBRARY_PROPERTIES = array(
        "library" => array('S', "PHP AMQP Lib"),
        "library_version" => array('S', "2.0"),
        "capabilities" => array(
            'F',
            array(
                'publisher_confirms' => array('t', true),
                'consumer_cancel_notify' => array('t', true),
                'exchange_exchange_bindings' => array('t', true),
                'basic.nack' => array('t', true)
            )
        )
    );
    
    protected $channel_max = 65535;
    
    protected $frame_max = 131072;

    /**
     * constructor parameters for clone
     * @var array
     */
    protected $construct_params;
    /**
     * close the connection in destructor
     * @var bool
     */
    protected $close_on_destruct = true ;

    /**
     * @var null|\PhpAmqpLib\Wire\IO\AbstractIO
     */
    protected $io = null;

    protected $wait_frame_reader;

    public function __construct($user, $password,
                                $vhost="/",$insist=false,
                                $login_method="AMQPLAIN",
                                $login_response=null,
                                $locale="en_US",
                                AbstractIO $io)
    {
        // save the params for the use of __clone
        $this->construct_params = func_get_args();

        $this->wait_frame_reader = new AMQPReader(null);

        if ($user && $password) {
            $login_response = new AMQPWriter();
            $login_response->write_table(array("LOGIN" => array('S',$user),
                "PASSWORD" => array('S',$password)));
            $login_response = substr($login_response->getvalue(),4); //Skip the length
        } else {
            $login_response = null;
        }

        $this->prepare_content_cache = array();
        $this->prepare_content_cache_max_size = 100;

        $d = self::$LIBRARY_PROPERTIES;
        while (true) {
            $this->channels = array();
            // The connection object itself is treated as channel 0
            parent::__construct($this, 0);

            $this->io = $io;
            $this->input = new AMQPReader(null, $this->io);

            $this->write($this->amqp_protocol_header);
            $this->wait(array($this->waitHelper->get_wait('connection.start')));
            $this->x_start_ok($d, $login_method, $login_response, $locale);

            $this->wait_tune_ok = true;
            while ($this->wait_tune_ok) {
                $this->wait(array(
                    $this->waitHelper->get_wait('connection.secure'),
                    $this->waitHelper->get_wait('connection.tune')
                ));
            }

            $host = $this->x_open($vhost,"", $insist);
            if (!$host) {
                return; // we weren't redirected
            }

            // we were redirected, close the socket, loop and try again
            $this->close_socket();
        }
    }
    /**
     * cloning will use the old properties to make a new connection to the same server
     */
    public function __clone()
    {
        call_user_func_array(array($this, '__construct'), $this->construct_params);
    }

    public function __destruct()
    {
        if ($this->close_on_destruct) {
            if (isset($this->input) && $this->input) {
                // close() always tries to connect to the server to shutdown
                // the connection. If the server has gone away, it will
                // throw an error in the connection class, so catch it
                // and shutdown quietly
                try {
                    $this->close();
                } catch (\Exception $e) { }
            }
        }
    }

    public function select($sec, $usec = 0)
    {
        return $this->getIO()->select($sec, $usec);
    }

    /**
     * allows to not close the connection
     * it`s useful after the fork when you don`t want to close parent process connection
     * @param bool $close
     */
    public function set_close_on_destruct($close = true)
    {
        $this->close_on_destruct = (bool) $close;
    }

    protected function close_socket()
    {
        if ($this->debug) {
            MiscHelper::debug_msg("closing socket");
        }

        $this->getIO()->close();
    }

    public function write($data)
    {
        if ($this->debug) {
            MiscHelper::debug_msg("< [hex]:\n" . MiscHelper::hexdump($data, $htmloutput = false, $uppercase = true, $return = true));
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

    public function get_free_channel_id()
    {
        for ($i=1; $i <= $this->channel_max; $i++) {
            if (!isset($this->channels[$i])) {
                return $i;
            }
        }

        throw new AMQPRuntimeException("No free channel ids");
    }

    public function send_content($channel, $class_id, $weight, $body_size,
                        $packed_properties, $body, $pkt = null)
    {
        $this->prepare_content($channel, $class_id, $weight, $body_size,
                        $packed_properties, $body, $pkt);
        $this->write($pkt->getvalue());
    }

    /**
     * returns a new AMQPWriter or mutates the provided $pkt
     */
    public function prepare_content($channel, $class_id, $weight, $body_size,
                        $packed_properties, $body, $pkt = null)
    {
        if (empty($pkt)) {
            $pkt = new AMQPWriter();
        }

        // Content already prepared ?
        $key_cache = "$channel|$packed_properties|$class_id|$weight";
        if (! isset($this->prepare_content_cache[$key_cache])) {
            $w = new AMQPWriter();
            $w->write_octet(2);
            $w->write_short($channel);
            $w->write_long(strlen($packed_properties)+12);
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
            $payload = substr($body,0, $this->frame_max-8);
            $body = substr($body,$this->frame_max-8);

            $pkt->write_octet(3);
            $pkt->write_short($channel);
            $pkt->write_long(strlen($payload));

            $pkt->write($payload);

            $pkt->write_octet(0xCE);
        }

        return $pkt;
    }

    protected function send_channel_method_frame($channel, $method_sig, $args="", $pkt=null)
    {
        $pkt = $this->prepare_channel_method_frame($channel, $method_sig, $args, $pkt);

        $this->write($pkt->getvalue());

        if ($this->debug) {
            $PROTOCOL_CONSTANTS_CLASS = self::$PROTOCOL_CONSTANTS_CLASS;
                MiscHelper::debug_msg("< " . MiscHelper::methodSig($method_sig) . ": " .
                           $PROTOCOL_CONSTANTS_CLASS::$GLOBAL_METHOD_NAMES[MiscHelper::methodSig($method_sig)]);
        }

    }

    /**
     * returns a new AMQPWriter or mutates the provided $pkt
     */
    protected function prepare_channel_method_frame($channel, $method_sig, $args="", $pkt = null)
    {
        if ($args instanceof AMQPWriter) {
            $args = $args->getvalue();
        }

        if (empty($pkt)) {
            $pkt = new AMQPWriter();
        }

        $pkt->write_octet(1);
        $pkt->write_short($channel);
        $pkt->write_long(strlen($args)+4);  // 4 = length of class_id and method_id
        // in payload

        $pkt->write_short($method_sig[0]); // class_id
        $pkt->write_short($method_sig[1]); // method_id
        $pkt->write($args);

        $pkt->write_octet(0xCE);

        if ($this->debug) {
            $PROTOCOL_CONSTANTS_CLASS = self::$PROTOCOL_CONSTANTS_CLASS;
                MiscHelper::debug_msg("< " . MiscHelper::methodSig($method_sig) . ": " .
                           $PROTOCOL_CONSTANTS_CLASS::$GLOBAL_METHOD_NAMES[MiscHelper::methodSig($method_sig)]);
        }

        return $pkt;
    }

    /**
     * Wait for a frame from the server
     */
    protected function wait_frame($timeout = 0)
    {
        $currentTimeout = $this->input->getTimeout();
        $this->input->setTimeout($timeout);

        try {
            // frame_type + channel_id + size
            $this->wait_frame_reader->reuse($this->input->read(AMQPReader::OCTET + AMQPReader::SHORT + AMQPReader::LONG));

            $frame_type = $this->wait_frame_reader->read_octet();
            $channel = $this->wait_frame_reader->read_short();
            $size = $this->wait_frame_reader->read_long();

            // payload + ch
            $this->wait_frame_reader->reuse($this->input->read($size + AMQPReader::OCTET));

            $payload = $this->wait_frame_reader->read($size);
            $ch = $this->wait_frame_reader->read_octet();
        } catch(AMQPTimeoutException $e) {
            $this->input->setTimeout($currentTimeout);

            throw $e;
        }

        $this->input->setTimeout($currentTimeout);

        if ($ch != 0xCE) {
            throw new AMQPRuntimeException(sprintf("Framing error, unexpected byte: %x", $ch));
        }

        return array($frame_type, $channel, $payload);
    }

    /**
     * Wait for a frame from the server destined for
     * a particular channel.
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
            array_push($this->channels[$frame_channel]->frame_queue,
                       array($frame_type, $payload));

            // If we just queued up a method for channel 0 (the Connection
            // itself) it's probably a close method in reaction to some
            // error, so deal with it right away.
            if (($frame_type == 1) && ($frame_channel == 0)) {
                $this->wait();
            }
        }
    }

    /**
     * Fetch a Channel object identified by the numeric channel_id, or
     * create that object if it doesn't already exist.
     */
    public function channel($channel_id = null)
    {
        if (isset($this->channels[$channel_id])) {
            return $this->channels[$channel_id];
        } else {
            $channel_id = $channel_id ? $channel_id : $this->get_free_channel_id();
            $ch = new AMQPChannel($this->connection, $channel_id);
            $this->channels[$channel_id] =  $ch;

            return $ch;
        }
    }

    /**
     * request a connection close
     */
    public function close($reply_code=0, $reply_text="", $method_sig=array(0, 0))
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->connectionClose(
            $reply_code,
            $reply_text,
            $method_sig[0],
            $method_sig[1]
        );
        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
                $this->waitHelper->get_wait('connection.close_ok')
            ));
    }

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

    protected function connection_close($args)
    {
        $reply_code = $args->read_short();
        $reply_text = $args->read_shortstr();
        $class_id = $args->read_short();
        $method_id = $args->read_short();

        $this->x_close_ok();

        throw new AMQPProtocolConnectionException($reply_code, $reply_text, array($class_id, $method_id));
    }


    /**
     * confirm a connection close
     */
    protected function x_close_ok()
    {
        $this->send_method_frame(array(10, 61));
        $this->do_close();
    }

    /**
     * confirm a connection close
     */
    protected function connection_close_ok($args)
    {
        $this->do_close();
    }

    protected function x_open($virtual_host, $capabilities="", $insist=false)
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
     * signal that the connection is ready
     */
    protected function connection_open_ok($args)
    {
        $this->known_hosts = $args->read_shortstr();
        if ($this->debug) {
          MiscHelper::debug_msg("Open OK! known_hosts: " . $this->known_hosts);
        }

        return null;
    }


    /**
     * asks the client to use a different server
     */
    protected function connection_redirect($args)
    {
        $host = $args->read_shortstr();
        $this->known_hosts = $args->read_shortstr();
        if ($this->debug) {
          MiscHelper::debug_msg("Redirected to [". $host . "], known_hosts [" . $this->known_hosts . "]" );
        }

        return $host;
    }

    /**
     * security mechanism challenge
     */
    protected function connection_secure($args)
    {
        $challenge = $args->read_longstr();
    }

    /**
     * security mechanism response
     */
    protected function x_secure_ok($response)
    {
        $args = new AMQPWriter();
        $args->write_longstr($response);
        $this->send_method_frame(array(10, 21), $args);
    }

    /**
     * start connection negotiation
     */
    protected function connection_start($args)
    {
        $this->version_major = $args->read_octet();
        $this->version_minor = $args->read_octet();
        $this->server_properties = $args->read_table();
        $this->mechanisms = explode(" ", $args->read_longstr());
        $this->locales = explode(" ", $args->read_longstr());

        if ($this->debug) {
          MiscHelper::debug_msg(sprintf("Start from server, version: %d.%d, properties: %s, mechanisms: %s, locales: %s",
                            $this->version_major,
                            $this->version_minor,
                            self::dump_table($this->server_properties),
                            implode(', ', $this->mechanisms),
                            implode(', ', $this->locales)));
        }

    }

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
     * propose connection tuning parameters
     */
    protected function connection_tune($args)
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
     * negotiate connection tuning parameters
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
     * get socket from current connection
     */
    public function getSocket()
    {
        return $this->sock;
    }

    /**
     * @return \PhpAmqpLib\Wire\IO\AbstractIO
     */
    protected function getIO() {
        return $this->io;
    }

}

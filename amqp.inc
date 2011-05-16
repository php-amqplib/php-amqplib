<?php
  /**
   * Simple AMQP client library for AMQP for protocol version 0.8
   *
   * http://code.google.com/p/php-amqplib/
   * Vadim Zaliva <lord@crocodile.org>
   *
   */

require_once('amqp_wire.inc');
require_once('hexdump.inc');

function debug_msg($s)
{
  echo $s, "\n";
}

function methodSig($a)
{
    if(is_string($a))
        return $a;
    else
        return sprintf("%d,%d",$a[0] ,$a[1]);
}
    

class AMQPException extends Exception
{
    public function __construct($reply_code, $reply_text, $method_sig)
    {
        parent::__construct($reply_text,$reply_code);

        $this->amqp_reply_code = $reply_code; // redundant, but kept for BC
        $this->amqp_reply_text = $reply_text; // redundant, but kept for BC
        $this->amqp_method_sig = $method_sig;

        $ms=methodSig($method_sig);
        if(array_key_exists($ms, AbstractChannel::$GLOBAL_METHOD_NAMES))
            $mn = AbstractChannel::$GLOBAL_METHOD_NAMES[$ms];
        else
            $mn = "";
        $this->args = array(
            $reply_code,
            $reply_text,
            $method_sig,
            $mn
        );
    }
}

class AMQPConnectionException extends AMQPException
{
    public function __construct($reply_code, $reply_text, $method_sig)
    {
        parent::__construct($reply_code, $reply_text, $method_sig);
    }
}

class AMQPChannelException extends AMQPException
{
    public function __construct($reply_code, $reply_text, $method_sig)
    {
        parent::__construct($reply_code, $reply_text, $method_sig);
    }

}

class AbstractChannel
{
    private static $CONTENT_METHODS = array(
        "60,60", // Basic.deliver
        "60,71", // Basic.get_ok
    );
    
    private static $CLOSE_METHODS = array(
        "10,60", // Connection.close
        "20,40", // Channel.close
    );

    // All the method names
    public static $GLOBAL_METHOD_NAMES = array(
    "10,10" => "Connection.start",
    "10,11" => "Connection.start_ok",
    "10,20" => "Connection.secure",
    "10,21" => "Connection.secure_ok",
    "10,30" => "Connection.tune",
    "10,31" => "Connection.tune_ok",
    "10,40" => "Connection.open",
    "10,41" => "Connection.open_ok",
    "10,50" => "Connection.redirect",
    "10,60" => "Connection.close",
    "10,61" => "Connection.close_ok",
    "20,10" => "Channel.open",
    "20,11" => "Channel.open_ok",
    "20,20" => "Channel.flow",
    "20,21" => "Channel.flow_ok",
    "20,30" => "Channel.alert",
    "20,40" => "Channel.close",
    "20,41" => "Channel.close_ok",
    "30,10" => "Channel.access_request",
    "30,11" => "Channel.access_request_ok",
    "40,10" => "Channel.exchange_declare",
    "40,11" => "Channel.exchange_declare_ok",
    "40,20" => "Channel.exchange_delete",
    "40,21" => "Channel.exchange_delete_ok",
    "50,10" => "Channel.queue_declare",
    "50,11" => "Channel.queue_declare_ok",
    "50,20" => "Channel.queue_bind",
    "50,21" => "Channel.queue_bind_ok",
    "50,30" => "Channel.queue_purge",
    "50,31" => "Channel.queue_purge_ok",
    "50,40" => "Channel.queue_delete",
    "50,41" => "Channel.queue_delete_ok",
    "50,50" => "Channel.queue_unbind",
    "50,51" => "Channel.queue_unbind_ok",
    "60,10" => "Channel.basic_qos",
    "60,11" => "Channel.basic_qos_ok",
    "60,20" => "Channel.basic_consume",
    "60,21" => "Channel.basic_consume_ok",
    "60,30" => "Channel.basic_cancel",
    "60,31" => "Channel.basic_cancel_ok",
    "60,40" => "Channel.basic_publish",
    "60,50" => "Channel.basic_return",
    "60,60" => "Channel.basic_deliver",
    "60,70" => "Channel.basic_get",
    "60,71" => "Channel.basic_get_ok",
    "60,72" => "Channel.basic_get_empty",
    "60,80" => "Channel.basic_ack",
    "60,90" => "Channel.basic_reject",
    "60,100" => "Channel.basic_recover",
    "90,10" => "Channel.tx_select",
    "90,11" => "Channel.tx_select_ok",
    "90,20" => "Channel.tx_commit",
    "90,21" => "Channel.tx_commit_ok",
    "90,30" => "Channel.tx_rollback",
    "90,31" => "Channel.tx_rollback_ok"
    );
    
    protected $debug;

    public function __construct($connection, $channel_id)
    {
        $this->connection = $connection;
        $this->channel_id = $channel_id;
        $connection->channels[$channel_id] = $this;
        $this->frame_queue = array();  // Lower level queue for frames
        $this->method_queue = array(); // Higher level queue for methods
        $this->auto_decode = false;
        $this->debug = defined('AMQP_DEBUG') ? AMQP_DEBUG : false;
    }
    
    public function getChannelId()
    {
      return $this->channel_id;
    }


    function dispatch($method_sig, $args, $content)
    {
        if(!array_key_exists($method_sig, $this->method_map))
            throw new Exception("Unknown AMQP method $method_sig");
        
        $amqp_method = $this->method_map[$method_sig];
        if($content == NULL)
            return call_user_func(array($this,$amqp_method), $args);
        else
            return call_user_func(array($this,$amqp_method), $args, $content);
    }

    function next_frame()
    {
        if($this->debug)
        {
          debug_msg("waiting for a new frame");
        }
        if($this->frame_queue != NULL)
            return array_pop($this->frame_queue);
        return $this->connection->wait_channel($this->channel_id);
    }
    
    protected function send_method_frame($method_sig, $args="")
    {
        $this->connection->send_channel_method_frame($this->channel_id, $method_sig, $args);
    }

    function wait_content()
    {
        $frm = $this->next_frame();
        $frame_type = $frm[0];
        $payload = $frm[1];
        if($frame_type != 2)
            throw new Exception("Expecting Content header");

        $payload_reader = new AMQPReader(substr($payload,0,12));
        $class_id = $payload_reader->read_short();
        $weight = $payload_reader->read_short();

        $body_size = $payload_reader->read_longlong();
        $msg = new AMQPMessage();
        $msg->load_properties(substr($payload,12));

        $body_parts = array();
        $body_received = 0;
        while(bccomp($body_size,$body_received)==1)
        {
            $frm = $this->next_frame();
            $frame_type = $frm[0];
            $payload = $frm[1];
            if($frame_type != 3)
                throw new Exception("Expecting Content body, received frame type $frame_type");
            $body_parts[] = $payload;
            $body_received = bcadd($body_received, strlen($payload));
        }

        $msg->body = implode("",$body_parts);

        if($this->auto_decode and isset($msg->content_encoding))
        {
            try
            {
                $msg->body = $msg->body->decode($msg->content_encoding);
            } catch (Exception $e) {
              if($this->debug)
              {
                debug_msg("Ignoring body decoding exception: " . $e->getMessage());
              }
            }
        }
        
        return $msg;
    }
    
    /**
     * Wait for some expected AMQP methods and dispatch to them.
     * Unexpected methods are queued up for later calls to this Python
     * method.
     */
    public function wait($allowed_methods=NULL)
    {
        if($allowed_methods)
        {
          if($this->debug)
          {
            debug_msg("waiting for " . implode(", ", $allowed_methods));
          }
        }
        else
        {
          if($this->debug)
          {
            debug_msg("waiting for any method");
          }
        }

        //Process deferred methods
        foreach($this->method_queue as $qk=>$queued_method)
        {
          if($this->debug)
          {
            debug_msg("checking queue method " . $qk);
          }
            
            $method_sig = $queued_method[0];
            if($allowed_methods==NULL || in_array($method_sig, $allowed_methods))
            {
                unset($this->method_queue[$qk]);
                
                if($this->debug)
                {
                  debug_msg("Executing queued method: $method_sig: " . AbstractChannel::$GLOBAL_METHOD_NAMES[methodSig($method_sig)]);
                }
                
                return $this->dispatch($queued_method[0],
                                       $queued_method[1],
                                       $queued_method[2]);
            }
        }
        
        // No deferred methods?  wait for new ones
        while(true)
        {
            $frm = $this->next_frame();
            $frame_type = $frm[0];
            $payload = $frm[1];
            
            if($frame_type != 1)
                throw new Exception("Expecting AMQP method, received frame type: $frame_type");

            if(strlen($payload) < 4)
                throw new Exception("Method frame too short");
            
            $method_sig_array = unpack("n2", substr($payload,0,4));
            $method_sig = "" . $method_sig_array[1] . "," . $method_sig_array[2];
            $args = new AMQPReader(substr($payload,4));

            if($this->debug)
            {
              debug_msg("> $method_sig: " . AbstractChannel::$GLOBAL_METHOD_NAMES[methodSig($method_sig)]);
            }
            
            
            if(in_array($method_sig, AbstractChannel::$CONTENT_METHODS))
                $content = $this->wait_content();
            else
                $content = NULL;
            
            if($allowed_methods==NULL ||
               in_array($method_sig,$allowed_methods) ||
               in_array($method_sig,AbstractChannel::$CLOSE_METHODS))
            {
                return $this->dispatch($method_sig, $args, $content);
            }
            
            // Wasn't what we were looking for? save it for later
            if($this->debug)
            {
              debug_msg("Queueing for later: $method_sig: " . AbstractChannel::$GLOBAL_METHOD_NAMES[methodSig($method_sig)]);
            }
            array_push($this->method_queue,array($method_sig, $args, $content));
        }
    }
    
}

class AMQPConnection extends AbstractChannel
{
    public static $AMQP_PROTOCOL_HEADER = "AMQP\x01\x01\x09\x01";
    
    public static $LIBRARY_PROPERTIES = array(
        "library" => array('S', "PHP Simple AMQP lib"),
        "library_version" => array('S', "0.1")
    );

    protected $method_map = array(
        "10,10" => "start",
        "10,20" => "secure",
        "10,30" => "tune",
        "10,41" => "open_ok",
        "10,50" => "redirect",
        "10,60" => "_close",
        "10,61" => "close_ok"
    );
    
    public function __construct($host, $port,
                                $user, $password,
                                $vhost="/",$insist=false,
                                $login_method="AMQPLAIN",
                                $login_response=NULL,
                                $locale="en_US",
                                $connection_timeout = 3,
                                $read_write_timeout = 3)
    {

        if($user && $password)
        {
            $login_response = new AMQPWriter();
            $login_response->write_table(array("LOGIN" => array('S',$user),
                                               "PASSWORD" => array('S',$password)));
            $login_response = substr($login_response->getvalue(),4); //Skip the length
        } else
            $login_response = NULL;
        

        $d = AMQPConnection::$LIBRARY_PROPERTIES;
        while(true)
        {
            $this->channels = array();
            // The connection object itself is treated as channel 0
            parent::__construct($this, 0);
            
            $this->channel_max = 65535;
            $this->frame_max = 131072;

            $errstr = $errno = NULL;
            $this->sock = NULL;
            if (!($this->sock = fsockopen($host,$port,$errno,$errstr,$connection_timeout)))
            {
                throw new Exception ("Error Connecting to server($errno): $errstr ");
            }
            
            stream_set_timeout($this->sock, $read_write_timeout);
            stream_set_blocking($this->sock, 1);
            $this->input = new AMQPReader(null, $this->sock);

            $this->write(AMQPConnection::$AMQP_PROTOCOL_HEADER);
            $this->wait(array("10,10"));
            $this->x_start_ok($d, $login_method, $login_response, $locale);
        
            $this->wait_tune_ok = true;
            while($this->wait_tune_ok)
            {
                $this->wait(array(
                                "10,20", // secure
                                "10,30", // tune
                            ));
            }

            $host = $this->x_open($vhost,"", $insist);
            if(!$host)
                return; // we weren't redirected

            // we were redirected, close the socket, loop and try again
            if($this->debug)
            {
              debug_msg("closing socket");
            }
            
            @fclose($this->sock); $this->sock=NULL;
        }
    }
   
    public function __destruct()
    {
        if(isset($this->input))
            if($this->input)
                $this->close();

        if($this->sock)
        {
          if($this->debug)
          {
            debug_msg("closing socket");
          }
          
          @fclose($this->sock);
        }
    }

    protected function write($data)
    {
        if($this->debug)
        {
          debug_msg("< [hex]:\n" . hexdump($data, $htmloutput = false, $uppercase = true, $return = true));
        }
        
        $len = strlen($data);
        while(true)
        {
            if(false === ($written = fwrite($this->sock, $data)))
            {
                throw new Exception ("Error sending data");
            }
            $len = $len - $written;
            if($len>0)
                $data=substr($data,0-$len);
            else
                break;
        }
    }
    
    protected function do_close()
    {
        if(isset($this->input))
            if($this->input)
            {
                $this->input->close();
                $this->input = NULL;
            }
        
        if($this->sock)
        {
            if($this->debug)
            {
              debug_msg("closing socket");
            }
            
            @fclose($this->sock);
            $this->sock = NULL;
        }
    }

    public function get_free_channel_id()
    {
        for($i=1;$i<=$this->channel_max;$i++)
            if(!array_key_exists($i,$this->channels))
                return $i;
        throw new Exception("No free channel ids");
    }

    public function send_content($channel, $class_id, $weight, $body_size,
                        $packed_properties, $body)
    {
        $pkt = new AMQPWriter();

        $pkt->write_octet(2);
        $pkt->write_short($channel);
        $pkt->write_long(strlen($packed_properties)+12);

        $pkt->write_short($class_id);
        $pkt->write_short($weight);
        $pkt->write_longlong($body_size);
        $pkt->write($packed_properties);

        $pkt->write_octet(0xCE);
        $pkt = $pkt->getvalue();
        $this->write($pkt);
        
        while($body)
        {
            $payload = substr($body,0, $this->frame_max-8);
            $body = substr($body,$this->frame_max-8);
            $pkt = new AMQPWriter();

            $pkt->write_octet(3);
            $pkt->write_short($channel);
            $pkt->write_long(strlen($payload));
            
            $pkt->write($payload);
            
            $pkt->write_octet(0xCE);
            $pkt = $pkt->getvalue();
            $this->write($pkt);
        }
    }

    protected function send_channel_method_frame($channel, $method_sig, $args="")
    {
        if($args instanceof AMQPWriter)
            $args = $args->getvalue();

        $pkt = new AMQPWriter();

        $pkt->write_octet(1);
        $pkt->write_short($channel);
        $pkt->write_long(strlen($args)+4);  // 4 = length of class_id and method_id
        // in payload

        $pkt->write_short($method_sig[0]); // class_id
        $pkt->write_short($method_sig[1]); // method_id
        $pkt->write($args);

        $pkt->write_octet(0xCE);
        $pkt = $pkt->getvalue();
        $this->write($pkt);

        if($this->debug)
        {
          debug_msg("< " . methodSig($method_sig) . ": " . AbstractChannel::$GLOBAL_METHOD_NAMES[methodSig($method_sig)]);
        }
        
    }

    /**
     * Wait for a frame from the server
     */
    protected function wait_frame()
    {
        $frame_type = $this->input->read_octet();
        $channel = $this->input->read_short();
        $size = $this->input->read_long();
        $payload = $this->input->read($size);
        
        $ch = $this->input->read_octet();
        if($ch != 0xCE)
            throw new Exception(sprintf("Framing error, unexpected byte: %x", $ch));
        
        return array($frame_type, $channel, $payload);
    }

    /**
     * Wait for a frame from the server destined for
     * a particular channel.
     */
    protected function wait_channel($channel_id)
    {
        while(true)
        {
            list($frame_type, $frame_channel, $payload) = $this->wait_frame();
            if($frame_channel == $channel_id)
                return array($frame_type, $payload);

            // Not the channel we were looking for.  Queue this frame
            //for later, when the other channel is looking for frames.
            array_push($this->channels[$frame_channel]->frame_queue,
                       array($frame_type, $payload));
            
            // If we just queued up a method for channel 0 (the Connection
            // itself) it's probably a close method in reaction to some
            // error, so deal with it right away.
            if(($frame_type == 1) && ($frame_channel == 0))
                $this->wait();
        }
    }

    /**
     * Fetch a Channel object identified by the numeric channel_id, or
     * create that object if it doesn't already exist.
     */
    public function channel($channel_id=NULL)
    {
        if(array_key_exists($channel_id,$this->channels))
            return $this->channels[$channel_id];
        
        return new AMQPChannel($this->connection, $channel_id);
    }

    /**
     * request a connection close
     */
    public function close($reply_code=0, $reply_text="", $method_sig=array(0, 0))
    {
        $args = new AMQPWriter();
        $args->write_short($reply_code);
        $args->write_shortstr($reply_text);
        $args->write_short($method_sig[0]); // class_id
        $args->write_short($method_sig[1]); // method_id
        $this->send_method_frame(array(10, 60), $args);
        return $this->wait(array(
                               "10,61",    // Connection.close_ok
                           ));
    }

    public static function dump_table($table)
    {
        $tokens = array();
        foreach ($table as $name => $value)
        {
            switch ($value[0])
            {
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

    protected function _close($args)
    {
        $reply_code = $args->read_short();
        $reply_text = $args->read_shortstr();
        $class_id = $args->read_short();
        $method_id = $args->read_short();

        $this->x_close_ok();
        
        throw new AMQPConnectionException($reply_code, $reply_text, array($class_id, $method_id));
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
    protected function close_ok($args)
    {
        $this->do_close();
    }

    protected function x_open($virtual_host, $capabilities="", $insist=false)
    {
        $args = new AMQPWriter();
        $args->write_shortstr($virtual_host);
        $args->write_shortstr($capabilities);
        $args->write_bit($insist);
        $this->send_method_frame(array(10, 40), $args);
        return $this->wait(array(
                               "10,41", // Connection.open_ok
                               "10,50"  // Connection.redirect
                           ));
    }


    /**
     * signal that the connection is ready
     */
    protected function open_ok($args)
    {
        $this->known_hosts = $args->read_shortstr();
        if($this->debug)
        {
          debug_msg("Open OK! known_hosts: " . $this->known_hosts);
        }
        
        return NULL;
    }


    /**
     * asks the client to use a different server
     */
    protected function redirect($args)
    {
        $host = $args->read_shortstr();
        $this->known_hosts = $args->read_shortstr();
        if($this->debug)
        {
          debug_msg("Redirected to [". $host . "], known_hosts [" . $this->known_hosts . "]" );
        }
        return $host;
    }

    /**
     * security mechanism challenge
     */
    protected function secure($args)
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
    protected function start($args)
    {
        $this->version_major = $args->read_octet();
        $this->version_minor = $args->read_octet();
        $this->server_properties = $args->read_table();
        $this->mechanisms = explode(" ", $args->read_longstr());
        $this->locales = explode(" ", $args->read_longstr());

        if($this->debug)
        {
          debug_msg(sprintf("Start from server, version: %d.%d, properties: %s, mechanisms: %s, locales: %s",
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
    protected function tune($args)
    {
        $v=$args->read_short();
        if($v)
            $this->channel_max = $v;
        $v=$args->read_long();
        if($v)
            $this->frame_max = $v;
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
        $this->wait_tune_ok = False;
    }

}

class AMQPChannel extends AbstractChannel
{
    protected $method_map = array(
        "20,11" => "open_ok",
        "20,20" => "flow",
        "20,21" => "flow_ok",
        "20,30" => "alert",
        "20,40" => "_close",
        "20,41" => "close_ok",
        "30,11" => "access_request_ok",
        "40,11" => "exchange_declare_ok",
        "40,21" => "exchange_delete_ok",
        "50,11" => "queue_declare_ok",
        "50,21" => "queue_bind_ok",
        "50,31" => "queue_purge_ok",
        "50,41" => "queue_delete_ok",
        "50,51" => "queue_unbind_ok",
        "60,11" => "basic_qos_ok",
        "60,21" => "basic_consume_ok",
        "60,31" => "basic_cancel_ok",
        "60,50" => "basic_return",
        "60,60" => "basic_deliver",
        "60,71" => "basic_get_ok",
        "60,72" => "basic_get_empty",
        "90,11" => "tx_select_ok",
        "90,21" => "tx_commit_ok",
        "90,31" => "tx_rollback_ok"
    );
    
    public function __construct($connection,
                                $channel_id=NULL,
                                $auto_decode=true)
    {

        if($channel_id == NULL)
            $channel_id = $connection->get_free_channel_id();

        parent::__construct($connection, $channel_id);
        
        if($this->debug)
        {
          debug_msg("using channel_id: " . $channel_id);
        }
        
        $this->default_ticket = 0;
        $this->is_open = false;
        $this->active = true; // Flow control
        $this->alerts = array();
        $this->callbacks = array();
        $this->auto_decode = $auto_decode;

        $this->x_open();
    }

    public function __destruct()
    {
        //TODO:???if($this->connection)
        //    $this->close("destroying channel");
    }

    /**
     * Tear down this object, after we've agreed to close with the server.
     */
    protected function do_close()
    {
        $this->is_open = false;
        unset($this->connection->channels[$this->channel_id]);
        $this->channel_id = $this->connection = NULL;
    }

    /**
     * This method allows the server to send a non-fatal warning to
     * the client.  This is used for methods that are normally
     * asynchronous and thus do not have confirmations, and for which
     * the server may detect errors that need to be reported.  Fatal
     * errors are handled as channel or connection exceptions; non-
     * fatal errors are sent through this method.
     */
    protected function alert($args)
    {
        $reply_code = $args->read_short();
        $reply_text = $args->read_shortstr();
        $details = $args->read_table();

        array_push($this->alerts,array($reply_code, $reply_text, $details));
    }

    /**
     * request a channel close
     */
    public function close($reply_code=0,
                          $reply_text="",
                          $method_sig=array(0, 0))
    {
        $args = new AMQPWriter();
        $args->write_short($reply_code);
        $args->write_shortstr($reply_text);
        $args->write_short($method_sig[0]); // class_id
        $args->write_short($method_sig[1]); // method_id
        $this->send_method_frame(array(20, 40), $args);
        return $this->wait(array(
                               "20,41"    // Channel.close_ok
                           ));
    }


    protected function _close($args)
    {
        $reply_code = $args->read_short();
        $reply_text = $args->read_shortstr();
        $class_id   = $args->read_short();
        $method_id  = $args->read_short();

        $this->send_method_frame(array(20, 41));
        $this->do_close();
        
        throw new AMQPChannelException($reply_code, $reply_text,
                                       array($class_id, $method_id));
    }
    
    /**
     * confirm a channel close
     */
    protected function close_ok($args)
    {
        $this->do_close();
    }

    /**
     * enable/disable flow from peer
     */
    public function flow($active)
    {
        $args = new AMQPWriter();
        $args->write_bit($active);
        $this->send_method_frame(array(20, 20), $args);
        return $this->wait(array(
                               "20,21"    //Channel.flow_ok
                           ));
    }

    protected function _flow($args)
    {
        $this->active = $args->read_bit();
        $this->x_flow_ok($this->active);
    }

    protected function x_flow_ok($active)
    {
        $args = new AMQPWriter();
        $args->write_bit($active);
        $this->send_method_frame(array(20, 21), $args);
    }

    protected function flow_ok($args)
    {
        return $args->read_bit();
    }
    
    protected function x_open($out_of_band="")
    {
        if($this->is_open)
            return;
        
        $args = new AMQPWriter();
        $args->write_shortstr($out_of_band);
        $this->send_method_frame(array(20, 10), $args);
        return $this->wait(array(
                               "20,11"    //Channel.open_ok
                           ));
    }
    
    protected function open_ok($args)
    {
        $this->is_open = true;
        if($this->debug)
        {
          debug_msg("Channel open");
        }
    }

    /**
     * request an access ticket
     */
    public function access_request($realm, $exclusive=false,
        $passive=false, $active=false, $write=false, $read=false)
    {
        $args = new AMQPWriter();
        $args->write_shortstr($realm);
        $args->write_bit($exclusive);
        $args->write_bit($passive);
        $args->write_bit($active);
        $args->write_bit($write);
        $args->write_bit($read);
        $this->send_method_frame(array(30, 10), $args);
        return $this->wait(array(
                               "30,11"    //Channel.access_request_ok
                           ));
    }

    /**
     * grant access to server resources
     */
    protected function access_request_ok($args)
    {
        $this->default_ticket = $args->read_short();
        return $this->default_ticket;
    }
        

    /**
     * declare exchange, create if needed
     */
    public function exchange_declare($exchange,
                                     $type,
                                     $passive=false,
                                     $durable=false,
                                     $auto_delete=true,
                                     $internal=false,
                                     $nowait=false,
                                     $arguments=NULL,
                                     $ticket=NULL)
    {
        if($arguments==NULL)
            $arguments = array();
        
        $args = new AMQPWriter();
        if($ticket != NULL)
            $args->write_short($ticket);
        else
            $args->write_short($this->default_ticket);
        $args->write_shortstr($exchange);
        $args->write_shortstr($type);
        $args->write_bit($passive);
        $args->write_bit($durable);
        $args->write_bit($auto_delete);
        $args->write_bit($internal);
        $args->write_bit($nowait);
        $args->write_table($arguments);
        $this->send_method_frame(array(40, 10), $args);

        if(!$nowait)
            return $this->wait(array(
                                   "40,11"    //Channel.exchange_declare_ok
                               ));
    }

    /**
     * confirms an exchange declaration
     */
    protected function exchange_declare_ok($args)
    {
    }

    /**
     * delete an exchange
     */
    public function exchange_delete($exchange, $if_unused=false,
        $nowait=false, $ticket=NULL)
    {
        $args = new AMQPWriter();
        if($ticket != NULL)
            $args->write_short($ticket);
        else
            $args->write_short($this->default_ticket);
        $args->write_shortstr($exchange);
        $args->write_bit($if_unused);
        $args->write_bit($nowait);
        $this->send_method_frame(array(40, 20), $args);

        if(!$nowait)
            return $this->wait(array(
                                   "40,21"    //Channel.exchange_delete_ok
                               ));
    }

    /**
     * confirm deletion of an exchange
     */
    protected function exchange_delete_ok($args)
    {
    }


    /**
     * bind queue to an exchange
     */
    public function queue_bind($queue, $exchange, $routing_key="",
        $nowait=false, $arguments=NULL, $ticket=NULL)
    {
        if($arguments == NULL)
            $arguments = array();

        $args = new AMQPWriter();
        if($ticket != NULL)
            $args->write_short($ticket);
        else
            $args->write_short($this->default_ticket);
        $args->write_shortstr($queue);
        $args->write_shortstr($exchange);
        $args->write_shortstr($routing_key);
        $args->write_bit($nowait);
        $args->write_table($arguments);
        $this->send_method_frame(array(50, 20), $args);

        if(!$nowait)
            return $this->wait(array(
                                   "50,21"    // Channel.queue_bind_ok
                               ));
    }

    /**
     * confirm bind successful
     */
    protected function queue_bind_ok($args)
    {
    }

    /**
     * unbind queue from an exchange
     */
    public function queue_unbind($queue, $exchange, $routing_key="",
        $arguments=NULL, $ticket=NULL)
    {
        if($arguments == NULL)
            $arguments = array();

        $args = new AMQPWriter();
        if($ticket != NULL)
            $args->write_short($ticket);
        else
            $args->write_short($this->default_ticket);
        $args->write_shortstr($queue);
        $args->write_shortstr($exchange);
        $args->write_shortstr($routing_key);
        $args->write_table($arguments);
        $this->send_method_frame(array(50, 50), $args);

        return $this->wait(array(
                               "50,51"    // Channel.queue_unbind_ok
                           ));
    }

    /**
     * confirm unbind successful
     */
    protected function queue_unbind_ok($args)
    {
    }

    /**
     * declare queue, create if needed
     */
    public function  queue_declare($queue="",
                                   $passive=false,
                                   $durable=false,
                                   $exclusive=false,
                                   $auto_delete=true,
                                   $nowait=false,
                                   $arguments=NULL,
                                   $ticket=NULL)
    {
        if($arguments == NULL)
            $arguments = array();

        $args = new AMQPWriter();
        if($ticket != NULL)
            $args->write_short($ticket);
        else
            $args->write_short($this->default_ticket);
        $args->write_shortstr($queue);
        $args->write_bit($passive);
        $args->write_bit($durable);
        $args->write_bit($exclusive);
        $args->write_bit($auto_delete);
        $args->write_bit($nowait);
        $args->write_table($arguments);
        $this->send_method_frame(array(50, 10), $args);

        if(!$nowait)
            return $this->wait(array(
                                   "50,11"    // Channel.queue_declare_ok
                               ));
    }

    /**
     * confirms a queue definition
     */
    protected function queue_declare_ok($args)
    {
        $queue = $args->read_shortstr();
        $message_count = $args->read_long();
        $consumer_count = $args->read_long();
        
        return array($queue, $message_count, $consumer_count);
    }

    /**
     * delete a queue
     */
    public function queue_delete($queue="", $if_unused=false, $if_empty=false,
        $nowait=false, $ticket=NULL)
    {
        $args = new AMQPWriter();
        if($ticket != NULL)
            $args->write_short($ticket);
        else
            $args->write_short($this->default_ticket);

        $args->write_shortstr($queue);
        $args->write_bit($if_unused);
        $args->write_bit($if_empty);
        $args->write_bit($nowait);
        $this->send_method_frame(array(50, 40), $args);

        if(!$nowait)
            return $this->wait(array(
                                   "50,41"    //Channel.queue_delete_ok
                               ));
    }

    /**
     * confirm deletion of a queue
     */
    protected function queue_delete_ok($args)
    {
        return $args->read_long();
    }

    /**
     * purge a queue
     */
    public function queue_purge($queue="", $nowait=false, $ticket=NULL)
    {
        $args = new AMQPWriter();
        if($ticket != NULL)
            $args->write_short($ticket);
        else
            $args->write_short($this->default_ticket);
        $args->write_shortstr($queue);
        $args->write_bit($nowait);
        $this->send_method_frame(array(50, 30), $args);

        if(!$nowait)
            return $this->wait(array(
                                   "50,31"    //Channel.queue_purge_ok
                               ));
    }

    /**
     * confirms a queue purge
     */
    protected function queue_purge_ok($args)
    {
        return $args->read_long();
    }

    /**
     * acknowledge one or more messages
     */
    public function basic_ack($delivery_tag, $multiple=false)
    {
        $args = new AMQPWriter();
        $args->write_longlong($delivery_tag);
        $args->write_bit($multiple);
        $this->send_method_frame(array(60, 80), $args);
    }

    /**
     * end a queue consumer
     */
    public function  basic_cancel($consumer_tag, $nowait=false)
    {
        $args = new AMQPWriter();
        $args->write_shortstr($consumer_tag);
        $args->write_bit($nowait);
        $this->send_method_frame(array(60, 30), $args);
        return $this->wait(array(
                               "60,31"    // Channel.basic_cancel_ok
                           ));
    }

    /**
     * confirm a cancelled consumer
     */
    protected function basic_cancel_ok($args)
    {
        $consumer_tag = $args->read_shortstr();
        unset($this->callbacks[$consumer_tag]);
    }

    /**
     * start a queue consumer
     */
    public function basic_consume($queue="", $consumer_tag="", $no_local=false,
                                  $no_ack=false, $exclusive=false, $nowait=false,
                                  $callback=NULL, $ticket=NULL)
    {
        $args = new AMQPWriter();
        if($ticket != NULL)
            $args->write_short($ticket);
        else
            $args->write_short($this->default_ticket);
        $args->write_shortstr($queue);
        $args->write_shortstr($consumer_tag);
        $args->write_bit($no_local);
        $args->write_bit($no_ack);
        $args->write_bit($exclusive);
        $args->write_bit($nowait);
        $this->send_method_frame(array(60, 20), $args);

        if(!$nowait)
            $consumer_tag = $this->wait(array(
                                            "60,21"    //Channel.basic_consume_ok
                                        ));
        
        $this->callbacks[$consumer_tag] = $callback;
        return $consumer_tag;
    }

    /**
     * confirm a new consumer
     */
    protected function basic_consume_ok($args)
    {
        return $args->read_shortstr();
    }

    /**
     * notify the client of a consumer message
     */
    protected function basic_deliver($args, $msg)
    {
        $consumer_tag = $args->read_shortstr();
        $delivery_tag = $args->read_longlong();
        $redelivered = $args->read_bit();
        $exchange = $args->read_shortstr();
        $routing_key = $args->read_shortstr();
        
        $msg->delivery_info = array(
            "channel" => $this,
            "consumer_tag" => $consumer_tag,
            "delivery_tag" => $delivery_tag,
            "redelivered" => $redelivered,
            "exchange" => $exchange,
            "routing_key" => $routing_key
        );

        if(array_key_exists($consumer_tag, $this->callbacks))
            $func = $this->callbacks[$consumer_tag];
        else
            $func = NULL;
        
        if($func!=NULL)
            call_user_func($func, $msg);
    }

    /**
     * direct access to a queue
     */
    public function basic_get($queue="", $no_ack=false, $ticket=NULL)
    {
        $args = new AMQPWriter();
        if($ticket != NULL)
            $args->write_short($ticket);
        else
            $args->write_short($this->default_ticket);
        $args->write_shortstr($queue);
        $args->write_bit($no_ack);
        $this->send_method_frame(array(60, 70), $args);
        return $this->wait(array(
                               "60,71",    //Channel.basic_get_ok
                               "60,72"     // Channel.basic_get_empty
                           ));
    }

    /**
     * indicate no messages available
     */
    protected function basic_get_empty($args)
    {
        $cluster_id = $args->read_shortstr();
    }

    /**
     * provide client with a message
     */
    protected function basic_get_ok($args, $msg)
    {
        $delivery_tag = $args->read_longlong();
        $redelivered = $args->read_bit();
        $exchange = $args->read_shortstr();
        $routing_key = $args->read_shortstr();
        $message_count = $args->read_long();

        $msg->delivery_info = array(
            "delivery_tag" => $delivery_tag,
            "redelivered" => $redelivered,
            "exchange" => $exchange,
            "routing_key" => $routing_key,
            "message_count" => $message_count
        );
        return $msg;
    }

    /**
     * publish a message
     */
    public function basic_publish($msg, $exchange="", $routing_key="",
                                  $mandatory=false, $immediate=false,
                                  $ticket=NULL)
    {
        $args = new AMQPWriter();
        if($ticket != NULL)
            $args->write_short($ticket);
        else
            $args->write_short($this->default_ticket);
        $args->write_shortstr($exchange);
        $args->write_shortstr($routing_key);
        $args->write_bit($mandatory);
        $args->write_bit($immediate);
        $this->send_method_frame(array(60, 40), $args);
        
        $this->connection->send_content($this->channel_id, 60, 0,
                                        strlen($msg->body),
                                        $msg->serialize_properties(),
                                        $msg->body);
    }
        

    /**
     * specify quality of service
     */
    public function basic_qos($prefetch_size, $prefetch_count, $a_global)
    {
        $args = new AMQPWriter();
        $args->write_long($prefetch_size);
        $args->write_short($prefetch_count);
        $args->write_bit($a_global);
        $this->send_method_frame(array(60, 10), $args);
        return $this->wait(array(
                               "60,11"    //Channel.basic_qos_ok
                           ));
    }


    /**
     * confirm the requested qos
     */
    protected function basic_qos_ok($args)
    {
    }

    /**
     * redeliver unacknowledged messages
     */
    public function basic_recover($requeue=false)
    {
        $args = new AMQPWriter();
        $args->write_bit($requeue);
        $this->send_method_frame(array(60, 100), $args);
    }

    /**
     * reject an incoming message
     */
    public function basic_reject($delivery_tag, $requeue)
    {
        $args = new AMQPWriter();
        $args->write_longlong($delivery_tag);
        $args->write_bit($requeue);
        $this->send_method_frame(array(60, 90), $args);
    }

    /**
     * return a failed message
     */
    protected function basic_return($args)
    {
        $reply_code = $args->read_short();
        $reply_text = $args->read_shortstr();
        $exchange = $args->read_shortstr();
        $routing_key = $args->read_shortstr();
        $msg = $this->wait();
    }


    public function tx_commit()
    {
        $this->send_method_frame(array(90, 20));
        return $this->wait(array(
                               "90,21"    //Channel.tx_commit_ok
                           ));
    }
    
    /**
     * confirm a successful commit
     */
    protected function tx_commit_ok($args)
    {
    }
    
    
    /**
     * abandon the current transaction
     */
    public function tx_rollback()
    {
        $this->send_method_frame(array(90, 30));
        return $this->wait(array(
                               "90,31"    //Channel.tx_rollback_ok
                           ));
    }

    /**
     * confirm a successful rollback
     */
    protected function tx_rollback_ok($args)
    {
    }

    /**
     * select standard transaction mode
     */
    public function tx_select()
    {
        $this->send_method_frame(array(90, 10));
        return $this->wait(array(
                               "90,11"    //Channel.tx_select_ok
                           ));
    }

    /**
     * confirm transaction mode
     */
    protected function tx_select_ok($args)
    {
    }

}

/**
 * A Message for use with the Channnel.basic_* methods.
 */
class AMQPMessage extends GenericContent
{
    protected static $PROPERTIES = array(
        "content_type" => "shortstr",
        "content_encoding" => "shortstr",
        "application_headers" => "table",
        "delivery_mode" => "octet",
        "priority" => "octet",
        "correlation_id" => "shortstr",
        "reply_to" => "shortstr",
        "expiration" => "shortstr",
        "message_id" => "shortstr",
        "timestamp" => "timestamp",
        "type" => "shortstr",
        "user_id" => "shortstr",
        "app_id" => "shortstr",
        "cluster_id" => "shortst"
    );

    public function __construct($body = '', $properties = null)
    {
        $this->body = $body;

        parent::__construct($properties, $prop_types=AMQPMessage::$PROPERTIES);
    }
}

?>

<?php

namespace PhpAmqpLib\Channel;

use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Exception\AMQPChannelException;
use PhpAmqpLib\Wire\AMQPWriter;

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

        if(isset($this->callbacks[$consumer_tag]))
        {
            $func = $this->callbacks[$consumer_tag];
        }
        else
        {
            $func = NULL;
        }

        if($func != NULL)
        {
            call_user_func($func, $msg);
        }
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
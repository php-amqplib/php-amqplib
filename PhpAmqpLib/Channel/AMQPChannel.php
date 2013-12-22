<?php

namespace PhpAmqpLib\Channel;

use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Exception\AMQPBasicCancelException;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Helper\MiscHelper;
use PhpAmqpLib\Wire\AMQPReader;
use PhpAmqpLib\Wire\AMQPWriter;

class AMQPChannel extends AbstractChannel
{
    public $callbacks = array();

    private $next_delivery_tag = 0;

    /**
     * @var Callable
     */
    private $ack_handler = null;

    /**
     * @var Callable
     */
    private $nack_handler = null;

    /**
     * If the channel is in confirm_publish mode this array will store all published messages
     * until they get ack'ed or nack'ed
     * @var \PhpAmqpLib\Message\AMQPMessage[]
     */
    private $published_messages = array();

    /**
     *
     * @var callable these parameters will be passed to function
     * 		in case of basic_return:
     * 	param int $reply_code
     * 	param string $reply_text
     * 	param string $exchange
     * 	param string $routing_key
     * 	param AMQPMessage $msg
     */
    protected $basic_return_callback = null;


    /**
     *
     * @var array
     * used to keep track of the messages that are going
     * to be batch published.
     */
    protected $batch_messages = array();

    /**
     * Circular buffer to speed up both basic_publish() and publish_batch().
     * Max size limited by $publish_cache_max_size.
     * @var array
     * @see basic_publish()
     * @see publish_batch()
     */
    private $publish_cache;

    /**
     * Maximal size of $publish_cache.
     * @var int
     */
    private $publish_cache_max_size;


    public function __construct($connection,
                                $channel_id=null,
                                $auto_decode=true)
    {
        if ($channel_id == null) {
            $channel_id = $connection->get_free_channel_id();
        }

        parent::__construct($connection, $channel_id);

        $this->publish_cache = array();
        $this->publish_cache_max_size = 100;

        if ($this->debug) {
          MiscHelper::debug_msg("using channel_id: " . $channel_id);
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
        $this->channel_id = $this->connection = null;
    }

    /**
     * Only for AMQP0.8.0
     * This method allows the server to send a non-fatal warning to
     * the client.  This is used for methods that are normally
     * asynchronous and thus do not have confirmations, and for which
     * the server may detect errors that need to be reported.  Fatal
     * errors are handled as channel or connection exceptions; non-
     * fatal errors are sent through this method.
     */
    protected function channel_alert($args)
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
        list($class_id, $method_id, $args) = $this->protocolWriter->channelClose(
                                         $reply_code,
                                         $reply_text,
                                         $method_sig[0],
                                         $method_sig[1]
                                      );

        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
                $this->waitHelper->get_wait('channel.close_ok')
        ));
    }


    protected function channel_close($args)
    {
        $reply_code = $args->read_short();
        $reply_text = $args->read_shortstr();
        $class_id   = $args->read_short();
        $method_id  = $args->read_short();

        $this->send_method_frame(array(20, 41));
        $this->do_close();

        throw new AMQPProtocolChannelException($reply_code, $reply_text,
                                       array($class_id, $method_id));
    }

    /**
     * confirm a channel close
     */
    protected function channel_close_ok($args)
    {
        $this->do_close();
    }

    /**
     * enable/disable flow from peer
     */
    public function flow($active)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->channelFlow($active);
        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
                $this->waitHelper->get_wait('channel.flow_ok')
            ));
    }

    protected function channel_flow($args)
    {
        $this->active = $args->read_bit();
        $this->x_flow_ok($this->active);
    }

    protected function x_flow_ok($active)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->channelFlow($active);
        $this->send_method_frame(array($class_id, $method_id), $args);
    }

    protected function channel_flow_ok($args)
    {
        return $args->read_bit();
    }

    protected function x_open($out_of_band="")
    {
        if ($this->is_open) {
            return;
        }

        list($class_id, $method_id, $args) = $this->protocolWriter->channelOpen($out_of_band);
        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
                $this->waitHelper->get_wait('channel.open_ok')
            ));
    }

    protected function channel_open_ok($args)
    {
        $this->is_open = true;
        if ($this->debug) {
          MiscHelper::debug_msg("Channel open");
        }
    }

    /**
     * request an access ticket
     */
    public function access_request($realm, $exclusive=false,
        $passive=false, $active=false, $write=false, $read=false)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->accessRequest($realm, $exclusive,
                                     $passive, $active,
                                     $write, $read);
        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
                $this->waitHelper->get_wait('access.request_ok')
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
                                     $arguments=null,
                                     $ticket=null)
    {

        $arguments = $this->getArguments($arguments);
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) =
            $this->protocolWriter->exchangeDeclare(
                $ticket, $exchange, $type, $passive, $durable,
                $auto_delete, $internal, $nowait, $arguments
            );

        $this->send_method_frame(array($class_id, $method_id), $args);

        if (!$nowait) {
            return $this->wait(array(
                    $this->waitHelper->get_wait('exchange.declare_ok')
                ));
        }

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
        $nowait=false, $ticket=null)
    {
        $ticket = $this->getTicket($ticket);
        list($class_id, $method_id, $args) = $this->protocolWriter->exchangeDelete($ticket, $exchange, $if_unused, $nowait);
        $this->send_method_frame(array($class_id, $method_id), $args);

        if (!$nowait) {
            return $this->wait(array(
                    $this->waitHelper->get_wait('exchange.delete_ok')
                ));
        }
    }

    /**
     * confirm deletion of an exchange
     */
    protected function exchange_delete_ok($args)
    {
    }

    /**
     * bind dest exchange to source exchange
     */
    public function exchange_bind($destination, $source, $routing_key="",
        $nowait=false, $arguments=null, $ticket=null)
    {
        $arguments = $this->getArguments($arguments);
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) = $this->protocolWriter->exchangeBind($ticket, $destination, $source, $routing_key, $nowait, $arguments);

        $this->send_method_frame(array($class_id, $method_id), $args);

        if (!$nowait) {
            return $this->wait(array(
                    $this->waitHelper->get_wait('exchange.bind_ok')
                ));
        }
    }

    /**
     * confirm bind successful
     */
    protected function exchange_bind_ok($args)
    {
    }

    /**
     * unbind dest exchange from source exchange
     */
    public function exchange_unbind($destination, $source, $routing_key="",
        $arguments=null, $ticket=null)
    {
        $arguments = $this->getArguments($arguments);
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) = $this->protocolWriter->exchangeUnbind($ticket, $destination, $source, $routing_key, $arguments);

        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
                $this->waitHelper->get_wait('exchange.unbind_ok')
            ));
    }

    /**
     * confirm unbind successful
     */
    protected function exchange_unbind_ok($args)
    {
    }


    /**
     * bind queue to an exchange
     */
    public function queue_bind($queue, $exchange, $routing_key="",
        $nowait=false, $arguments=null, $ticket=null)
    {
        $arguments = $this->getArguments($arguments);
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) = $this->protocolWriter->queueBind($ticket, $queue, $exchange, $routing_key, $nowait, $arguments);

        $this->send_method_frame(array($class_id, $method_id), $args);

        if (!$nowait) {
            return $this->wait(array(
                    $this->waitHelper->get_wait('queue.bind_ok')
                ));
        }
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
        $arguments=null, $ticket=null)
    {
        $arguments = $this->getArguments($arguments);
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) = $this->protocolWriter->queueUnbind($ticket, $queue, $exchange, $routing_key, $arguments);

        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
                $this->waitHelper->get_wait('queue.unbind_ok')
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
                                   $arguments=null,
                                   $ticket=null)
    {
        $arguments = $this->getArguments($arguments);
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) =
            $this->protocolWriter->queueDeclare(
                $ticket, $queue, $passive, $durable, $exclusive,
                $auto_delete, $nowait, $arguments
            );
        $this->send_method_frame(array($class_id, $method_id), $args);

        if (!$nowait) {
            return $this->wait(array(
                    $this->waitHelper->get_wait('queue.declare_ok')
                ));
        }
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
        $nowait=false, $ticket=null)
    {
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) = $this->protocolWriter->queueDelete($ticket, $queue, $if_unused, $if_empty, $nowait);

        $this->send_method_frame(array($class_id, $method_id), $args);

        if (!$nowait) {
            return $this->wait(array(
                    $this->waitHelper->get_wait('queue.delete_ok')
                ));
        }
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
    public function queue_purge($queue="", $nowait=false, $ticket=null)
    {
        $ticket = $this->getTicket($ticket);
        list($class_id, $method_id, $args) = $this->protocolWriter->queuePurge($ticket, $queue, $nowait);

        $this->send_method_frame(array($class_id, $method_id), $args);

        if (!$nowait) {
            return $this->wait(array(
                    $this->waitHelper->get_wait('queue.purge_ok')
                ));
        }
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
        list($class_id, $method_id, $args) = $this->protocolWriter->basicAck($delivery_tag, $multiple);
        $this->send_method_frame(array($class_id, $method_id), $args);
    }

    /**
     * Called when the server sends a basic.ack
     *
     * @param \PhpAmqpLib\Wire\AMQPReader $args
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    protected function basic_ack_from_server(\PhpAmqpLib\Wire\AMQPReader $args)
    {
        $delivery_tag = $args->read_longlong();
        $multiple     = (bool) $args->read_bit();

        if (!isset($this->published_messages[$delivery_tag])) {
            throw new \PhpAmqpLib\Exception\AMQPRuntimeException(sprintf(
                    'Server ack\'ed unknown delivery_tag %s',
                    $delivery_tag
                )
            );
        }

        $this->internal_ack_handler($delivery_tag, $multiple, $this->ack_handler);
    }

    /**
     * Called when the server sends a basic.nack
     *
     * @param $args
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    protected function basic_nack_from_server($args)
    {
        $delivery_tag = $args->read_longlong();
        $multiple     = (bool) $args->read_bit();

        if (!isset($this->published_messages[$delivery_tag])) {
            throw new \PhpAmqpLib\Exception\AMQPRuntimeException(sprintf(
                    'Server nack\'ed unknown delivery_tag %s',
                    $delivery_tag
                )
            );
        }

        $this->internal_ack_handler($delivery_tag, $multiple, $this->nack_handler);
    }

    /**
     * Handles the deletion of messages from this->publishedMessages and dispatches them to the $handler
     *
     * @param $delivery_tag
     * @param $multiple
     * @param $handler
     */
    protected function internal_ack_handler($delivery_tag, $multiple, $handler)
    {
        if ($multiple) {
            $keys = $this->get_keys_less_or_equal($this->published_messages, $delivery_tag);

            foreach ($keys as $key) {
                $this->internal_ack_handler($key, false, $handler);
            }
        } else {
            $message = $this->get_and_unset_message($delivery_tag);
            $this->dispatch_to_handler($handler, array($message));
        }
    }

    protected function get_keys_less_or_equal(array $array, $value)
    {
        $keys = array_reduce(
            array_keys($array),
            function ($keys, $key) use ($value) {
                if (bccomp($key, $value) <= 0) {
                    $keys[] = $key;
                }

                return $keys;
            },
            array()
        );

        return $keys;
    }

    protected function dispatch_to_handler($handler, array $arguments)
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, $arguments);
        }
    }

    /**
     * reject one or several received messages.
     */
    public function basic_nack($delivery_tag, $multiple=false, $requeue=false)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->basicNack($delivery_tag, $multiple, $requeue);
        $this->send_method_frame(array($class_id, $method_id), $args);
    }

    /**
     * end a queue consumer
     */
    public function basic_cancel($consumer_tag, $nowait=false)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->basicCancel($consumer_tag, $nowait);
        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
                $this->waitHelper->get_wait('basic.cancel_ok')
            ));
    }

    protected function basic_cancel_from_server(AMQPReader $args)
    {
        $consumerTag = $args->read_shortstr();

        throw new AMQPBasicCancelException($consumerTag);
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
                                  $callback=null, $ticket=null, $arguments = array())
    {
        $ticket = $this->getTicket($ticket);
        list($class_id, $method_id, $args) =
            $this->protocolVersion == '0.9.1'
                ? $this->protocolWriter->basicConsume(
                    $ticket, $queue, $consumer_tag, $no_local,
                    $no_ack, $exclusive, $nowait, $arguments)
                : $this->protocolWriter->basicConsume(
                    $ticket, $queue, $consumer_tag, $no_local,
                    $no_ack, $exclusive, $nowait);

        $this->send_method_frame(array($class_id, $method_id), $args);

        if (!$nowait) {
            $consumer_tag = $this->wait(array(
                    $this->waitHelper->get_wait('basic.consume_ok')
                ));
        }

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

        if (isset($this->callbacks[$consumer_tag])) {
            $func = $this->callbacks[$consumer_tag];
        } else {
            $func = null;
        }

        if ($func != null) {
            call_user_func($func, $msg);
        }
    }

    /**
     * direct access to a queue
     * if no message was available in the queue, return null
     * @return null|AMQPMessage
     */
    public function basic_get($queue="", $no_ack=false, $ticket=null)
    {
        $ticket = $this->getTicket($ticket);
        list($class_id, $method_id, $args) = $this->protocolWriter->basicGet($ticket, $queue, $no_ack);

        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
                $this->waitHelper->get_wait('basic.get_ok'),
                $this->waitHelper->get_wait('basic.get_empty')
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

    private function pre_publish($exchange, $routing_key, $mandatory, $immediate, $ticket)
    {
        $cache_key = "$exchange|$routing_key|$mandatory|$immediate|$ticket";
        if (! isset($this->publish_cache[$cache_key])) {
            $ticket = $this->getTicket($ticket);
            list($class_id, $method_id, $args) =
                $this->protocolWriter->basicPublish($ticket, $exchange, $routing_key, $mandatory, $immediate);
            $pkt = $this->prepare_method_frame(array($class_id, $method_id), $args);
            $this->publish_cache[$cache_key] = $pkt->getvalue();
            if (count($this->publish_cache) > $this->publish_cache_max_size) {
                reset($this->publish_cache);
                $old_key = key($this->publish_cache);
                unset($this->publish_cache[$old_key]);
            }
        }
        return $this->publish_cache[$cache_key];
    }

    /**
     * publish a message
     */
    public function basic_publish($msg, $exchange="", $routing_key="",
                                  $mandatory=false, $immediate=false,
                                  $ticket=null)
    {
        $pkt = new AMQPWriter();
        $pkt->write($this->pre_publish($exchange, $routing_key, $mandatory, $immediate, $ticket));

        $this->connection->send_content($this->channel_id, 60, 0,
                                        strlen($msg->body),
                                        $msg->serialize_properties(),
                                        $msg->body, $pkt);

        if ($this->next_delivery_tag > 0) {
            $this->published_messages[$this->next_delivery_tag] = $msg;
            $this->next_delivery_tag                           = bcadd($this->next_delivery_tag, '1');
        }
    }

    public function batch_basic_publish($msg, $exchange="", $routing_key="",
                                  $mandatory=false, $immediate=false,
                                  $ticket=null)
    {
        $this->batch_messages[] = func_get_args();
    }

    public function publish_batch()
    {
        if (!empty($this->batch_messages)) {

            $pkt = new AMQPWriter();

            foreach ($this->batch_messages as $m) {
                $msg = $m[0];
                $exchange = isset($m[1]) ? $m[1] : "";
                $routing_key = isset($m[2]) ? $m[2] : "";
                $mandatory = isset($m[3]) ? $m[3] : false;
                $immediate = isset($m[4]) ? $m[4] : false;
                $ticket = isset($m[5]) ? $m[5] : null;
                $pkt->write($this->pre_publish($exchange, $routing_key, $mandatory, $immediate, $ticket));

                $this->connection->prepare_content($this->channel_id, 60, 0,
                                                strlen($msg->body),
                                                $msg->serialize_properties(),
                                                $msg->body, $pkt);

                if ($this->next_delivery_tag > 0) {
                    $this->published_messages[$this->next_delivery_tag] = $msg;
                    $this->next_delivery_tag                           = bcadd($this->next_delivery_tag, '1');
                }
            }
            //call write here
            $this->connection->write($pkt->getvalue());
            $this->batch_messages = array();

        }
    }

    /**
     * specify quality of service
     */
    public function basic_qos($prefetch_size, $prefetch_count, $a_global)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->basicQos($prefetch_size, $prefetch_count, $a_global);
        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
                $this->waitHelper->get_wait('basic.qos_ok')
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
        list($class_id, $method_id, $args) = $this->protocolWriter->basicRecover($requeue);
        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
                $this->waitHelper->get_wait('basic.recover_ok')
        ));
    }

    /**
     * confirm the requested recover
     */
    protected function basic_recover_ok($args)
    {
    }

    /**
     * reject an incoming message
     */
    public function basic_reject($delivery_tag, $requeue)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->basicReject($delivery_tag, $requeue);
        $this->send_method_frame(array($class_id, $method_id), $args);
    }

    /**
     * return a failed message
     */
    protected function basic_return($args, $msg)
    {
        $reply_code = $args->read_short();
        $reply_text = $args->read_shortstr();
        $exchange = $args->read_shortstr();
        $routing_key = $args->read_shortstr();

        if ( !is_null($this->basic_return_callback )) {
            call_user_func_array($this->basic_return_callback, array(
                $reply_code,
                $reply_text,
                $exchange,
                $routing_key,
                $msg,
            ));
        } elseif ($this->debug) {
            MiscHelper::debug_msg("Skipping unhandled basic_return message");
        }
    }
    public function tx_commit()
    {
        $this->send_method_frame(array(90, 20));

        return $this->wait(array(
                $this->waitHelper->get_wait('tx.commit_ok')
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
                $this->waitHelper->get_wait('tx.rollback_ok')
        ));
    }

    /**
     * confirm a successful rollback
     */
    protected function tx_rollback_ok($args)
    {
    }

    /**
     * Puts the channel into confirm mode. Beware that only non-transactional channels may be put into confirm mode
     * and vice versa
     *
     * @param bool $nowait if nowait is true the method will not wait for an answer of the server and return immediately.
     *                     defaults to false
     */
    public function confirm_select($nowait = false)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->confirmSelect($nowait);

        $this->send_method_frame(array($class_id, $method_id), $args);

        if (!$nowait) {
            $this->wait(
                array(
                    $this->waitHelper->get_wait('confirm.select_ok')
                )
            );
            $this->next_delivery_tag = 1;
        }
    }

    public function confirm_select_ok()
    {
    }

    /**
     * Waits for pending acks and nacks from the server. If there are no pending acks, the method returns immediately.
     *
     * @param int $timeout If set to value > 0 the method will wait at most $timeout seconds for pending acks.
     */
    public function wait_for_pending_acks($timeout = 0)
    {
        $functions = array(
            $this->waitHelper->get_wait('basic.ack'),
            $this->waitHelper->get_wait('basic.nack'),
        );

        while (count($this->published_messages) !== 0) {
            if ($timeout > 0) {
                $this->wait($functions, true, $timeout);
            } else {
                $this->wait($functions);
            }
        }
    }

    /**
     * select standard transaction mode
     */
    public function tx_select()
    {
        $this->send_method_frame(array(90, 10));

        return $this->wait(array(
                $this->waitHelper->get_wait('tx.select_ok')
        ));
    }

    /**
     * confirm transaction mode
     */
    protected function tx_select_ok($args)
    {
    }

    protected function getArguments($arguments)
    {
        return (null === $arguments) ? array() : $arguments;
    }

    protected function getTicket($ticket)
    {
        return (null === $ticket) ? $this->default_ticket : $ticket;
    }

    /**
     * Helper method to get a particular method from $this->publishedMessages, removes it from the array and returns it.
     *
     * @param $index
     * @return \PhpAmqpLib\Message\AMQPMessage
     */
    protected function get_and_unset_message($index)
    {
        $message = $this->published_messages[$index];
        unset($this->published_messages[$index]);

        return $message;
    }

    /**
     * set callback for basic_return
     * @param  callable                  $callback
     * @throws \InvalidArgumentException if $callback is not callable
     */
    public function set_return_listener($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException("$callback should be callable.");
        }
        $this->basic_return_callback = $callback;
    }

    /**
     * Sets a handler which called for any message nack'ed by the server, with the AMQPMessage as first argument.
     *
     * @param callable $callback
     */
    public function set_nack_handler($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException("$callback should be callable.");
        }
        $this->nack_handler = $callback;
    }

    /**
     * Sets a handler which called for any message ack'ed by the server, with the AMQPMessage as first argument.
     *
     * @param callable $callback
     */
    public function set_ack_handler($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException("$callback should be callable.");
        }
        $this->ack_handler = $callback;
    }
}

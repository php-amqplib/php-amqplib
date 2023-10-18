<?php

namespace PhpAmqpLib\Channel;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPBasicCancelException;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionBlockedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPNoDataException;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Helper\Assert;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire;
use PhpAmqpLib\Wire\AMQPReader;
use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Wire\AMQPWriter;

class AMQPChannel extends AbstractChannel
{
    /**
     * @var callable[]
     * @internal Use is_consuming() to check if there is active callbacks
     */
    public $callbacks = array();

    /** @var bool Whether or not the channel has been "opened" */
    protected $is_open = false;

    /** @var int */
    protected $default_ticket = 0;

    /** @var bool */
    protected $active = true;

    /** @var bool */
    protected $stopConsume = false;

    /** @var array */
    protected $alerts = array();

    /** @var bool */
    protected $auto_decode;

    /**
     * These parameters will be passed to function in case of basic_return:
     *    param int $reply_code
     *    param string $reply_text
     *    param string $exchange
     *    param string $routing_key
     *    param AMQPMessage $msg
     *
     * @var null|callable
     */
    protected $basic_return_callback;

    /** @var array Used to keep track of the messages that are going to be batch published. */
    protected $batch_messages = array();

    /**
     * If the channel is in confirm_publish mode this array will store all published messages
     * until they get ack'ed or nack'ed
     *
     * @var AMQPMessage[]
     */
    private $published_messages = array();

    /** @var int */
    private $next_delivery_tag = 0;

    /** @var null|callable */
    private $ack_handler;

    /** @var null|callable */
    private $nack_handler;

    /**
     * Circular buffer to speed up both basic_publish() and publish_batch().
     * Max size limited by $publish_cache_max_size.
     *
     * @var array
     * @see basic_publish()
     * @see publish_batch()
     */
    private $publish_cache = array();

    /**
     * Maximal size of $publish_cache
     *
     * @var int
     */
    private $publish_cache_max_size = 100;

    /**
     * Maximum time to wait for operations on this channel, in seconds.
     * @var float
     */
    protected $channel_rpc_timeout;

    /**
     * @param AbstractConnection $connection
     * @param int|null $channel_id
     * @param bool $auto_decode
     * @param int|float $channel_rpc_timeout
     * @throws \PhpAmqpLib\Exception\AMQPOutOfBoundsException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @throws \PhpAmqpLib\Exception\AMQPConnectionClosedException
     */
    public function __construct($connection, $channel_id = null, $auto_decode = true, $channel_rpc_timeout = 0)
    {
        if ($channel_id == null) {
            $channel_id = $connection->get_free_channel_id();
        }

        parent::__construct($connection, $channel_id);

        $this->debug->debug_msg('using channel_id: ' . $channel_id);

        $this->auto_decode = $auto_decode;
        $this->channel_rpc_timeout = $channel_rpc_timeout;

        try {
            $this->x_open();
        } catch (\Exception $e) {
            $this->close();
            throw $e;
        }
    }

    /**
     * @return bool
     */
    public function is_open()
    {
        return $this->is_open;
    }

    /**
     * Tear down this object, after we've agreed to close with the server.
     */
    protected function do_close()
    {
        if ($this->channel_id !== null) {
            unset($this->connection->channels[$this->channel_id]);
        }
        $this->channel_id = $this->connection = null;
        $this->is_open = false;
        $this->callbacks = array();
    }

    /**
     * Only for AMQP0.8.0
     * This method allows the server to send a non-fatal warning to
     * the client.  This is used for methods that are normally
     * asynchronous and thus do not have confirmations, and for which
     * the server may detect errors that need to be reported.  Fatal
     * errors are handled as channel or connection exceptions; non-
     * fatal errors are sent through this method.
     *
     * @param AMQPReader $reader
     */
    protected function channel_alert(AMQPReader $reader): void
    {
        $reply_code = $reader->read_short();
        $reply_text = $reader->read_shortstr();
        $details = $reader->read_table();
        array_push($this->alerts, array($reply_code, $reply_text, $details));
    }

    /**
     * Request a channel close
     *
     * @param int $reply_code
     * @param string $reply_text
     * @param array $method_sig
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function close($reply_code = 0, $reply_text = '', $method_sig = array(0, 0))
    {
        $this->callbacks = array();
        if ($this->is_open === false || $this->connection === null) {
            $this->do_close();

            return null; // already closed
        }
        list($class_id, $method_id, $args) = $this->protocolWriter->channelClose(
            $reply_code,
            $reply_text,
            $method_sig[0],
            $method_sig[1]
        );

        try {
            $this->send_method_frame(array($class_id, $method_id), $args);
        } catch (\Exception $e) {
            $this->do_close();

            throw $e;
        }

        return $this->wait(array(
            $this->waitHelper->get_wait('channel.close_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * @param AMQPReader $reader
     * @throws AMQPProtocolChannelException
     */
    protected function channel_close(AMQPReader $reader): void
    {
        $reply_code = $reader->read_short();
        $reply_text = $reader->read_shortstr();
        $class_id = $reader->read_short();
        $method_id = $reader->read_short();

        $this->send_method_frame(array(20, 41));
        $this->do_close();

        throw new AMQPProtocolChannelException($reply_code, $reply_text, array($class_id, $method_id));
    }

    /**
     * Confirm a channel close
     * Alias of AMQPChannel::do_close()
     */
    protected function channel_close_ok()
    {
        $this->do_close();
    }

    /**
     * Enables/disables flow from peer
     *
     * @param bool $active
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function flow($active)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->channelFlow($active);
        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
            $this->waitHelper->get_wait('channel.flow_ok')
        ), false, $this->channel_rpc_timeout);
    }

    protected function channel_flow(AMQPReader $reader): void
    {
        $this->active = $reader->read_bit();
        $this->x_flow_ok($this->active);
    }

    /**
     * @param bool $active
     */
    protected function x_flow_ok($active)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->channelFlow($active);
        $this->send_method_frame(array($class_id, $method_id), $args);
    }

    protected function channel_flow_ok(AMQPReader $reader): bool
    {
        return $reader->read_bit();
    }

    /**
     * @param string $out_of_band
     * @throws \PhpAmqpLib\Exception\AMQPOutOfBoundsException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @throws \PhpAmqpLib\Exception\AMQPConnectionClosedException
     * @return mixed
     */
    protected function x_open($out_of_band = '')
    {
        if ($this->is_open) {
            return null;
        }

        list($class_id, $method_id, $args) = $this->protocolWriter->channelOpen($out_of_band);
        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
            $this->waitHelper->get_wait('channel.open_ok')
        ), false, $this->channel_rpc_timeout);
    }

    protected function channel_open_ok()
    {
        $this->is_open = true;

        $this->debug->debug_msg('Channel open');
    }

    /**
     * Requests an access ticket
     *
     * @param string $realm
     * @param bool $exclusive
     * @param bool $passive
     * @param bool $active
     * @param bool $write
     * @param bool $read
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function access_request(
        $realm,
        $exclusive = false,
        $passive = false,
        $active = false,
        $write = false,
        $read = false
    ) {
        list($class_id, $method_id, $args) = $this->protocolWriter->accessRequest(
            $realm,
            $exclusive,
            $passive,
            $active,
            $write,
            $read
        );

        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
            $this->waitHelper->get_wait('access.request_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Grants access to server resources
     *
     * @param AMQPReader $reader
     * @return int
     */
    protected function access_request_ok(AMQPReader $reader): int
    {
        $this->default_ticket = $reader->read_short();

        return $this->default_ticket;
    }

    /**
     * Declares exchange
     *
     * @param string $exchange
     * @param string $type
     * @param bool $passive
     * @param bool $durable
     * @param bool $auto_delete
     * @param bool $internal
     * @param bool $nowait
     * @param AMQPTable|array $arguments
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed|null
     */
    public function exchange_declare(
        $exchange,
        $type,
        $passive = false,
        $durable = false,
        $auto_delete = true,
        $internal = false,
        $nowait = false,
        $arguments = array(),
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) = $this->protocolWriter->exchangeDeclare(
            $ticket,
            $exchange,
            $type,
            $passive,
            $durable,
            $auto_delete,
            $internal,
            $nowait,
            $arguments
        );

        $this->send_method_frame(array($class_id, $method_id), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->get_wait('exchange.declare_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirms an exchange declaration
     */
    protected function exchange_declare_ok()
    {
    }

    /**
     * Deletes an exchange
     *
     * @param string $exchange
     * @param bool $if_unused
     * @param bool $nowait
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed|null
     */
    public function exchange_delete(
        $exchange,
        $if_unused = false,
        $nowait = false,
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);
        list($class_id, $method_id, $args) = $this->protocolWriter->exchangeDelete(
            $ticket,
            $exchange,
            $if_unused,
            $nowait
        );

        $this->send_method_frame(array($class_id, $method_id), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->get_wait('exchange.delete_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirms deletion of an exchange
     */
    protected function exchange_delete_ok()
    {
    }

    /**
     * Binds dest exchange to source exchange
     *
     * @param string $destination
     * @param string $source
     * @param string $routing_key
     * @param bool $nowait
     * @param \PhpAmqpLib\Wire\AMQPTable|array $arguments
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed|null
     */
    public function exchange_bind(
        $destination,
        $source,
        $routing_key = '',
        $nowait = false,
        $arguments = array(),
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) = $this->protocolWriter->exchangeBind(
            $ticket,
            $destination,
            $source,
            $routing_key,
            $nowait,
            $arguments
        );

        $this->send_method_frame(array($class_id, $method_id), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->get_wait('exchange.bind_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirms bind successful
     */
    protected function exchange_bind_ok()
    {
    }

    /**
     * Unbinds dest exchange from source exchange
     *
     * @param string $destination
     * @param string $source
     * @param string $routing_key
     * @param bool $nowait
     * @param \PhpAmqpLib\Wire\AMQPTable|array $arguments
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function exchange_unbind(
        $destination,
        $source,
        $routing_key = '',
        $nowait = false,
        $arguments = array(),
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) = $this->protocolWriter->exchangeUnbind(
            $ticket,
            $destination,
            $source,
            $routing_key,
            $nowait,
            $arguments
        );

        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
            $this->waitHelper->get_wait('exchange.unbind_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirms unbind successful
     */
    protected function exchange_unbind_ok()
    {
    }

    /**
     * Binds queue to an exchange
     *
     * @param string $queue
     * @param string $exchange
     * @param string $routing_key
     * @param bool $nowait
     * @param \PhpAmqpLib\Wire\AMQPTable|array $arguments
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed|null
     */
    public function queue_bind(
        $queue,
        $exchange,
        $routing_key = '',
        $nowait = false,
        $arguments = array(),
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) = $this->protocolWriter->queueBind(
            $ticket,
            $queue,
            $exchange,
            $routing_key,
            $nowait,
            $arguments
        );

        $this->send_method_frame(array($class_id, $method_id), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->get_wait('queue.bind_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirms bind successful
     */
    protected function queue_bind_ok()
    {
    }

    /**
     * Unbind queue from an exchange
     *
     * @param string $queue
     * @param string $exchange
     * @param string $routing_key
     * @param \PhpAmqpLib\Wire\AMQPTable|array $arguments
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function queue_unbind(
        $queue,
        $exchange,
        $routing_key = '',
        $arguments = array(),
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) = $this->protocolWriter->queueUnbind(
            $ticket,
            $queue,
            $exchange,
            $routing_key,
            $arguments
        );

        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
            $this->waitHelper->get_wait('queue.unbind_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirms unbind successful
     */
    protected function queue_unbind_ok()
    {
    }

    /**
     * Declares queue, creates if needed
     *
     * @param string $queue
     * @param bool $passive
     * @param bool $durable
     * @param bool $exclusive
     * @param bool $auto_delete
     * @param bool $nowait
     * @param array|AMQPTable $arguments
     * @param int|null $ticket
     * @return array|null
     *@throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     */
    public function queue_declare(
        $queue = '',
        $passive = false,
        $durable = false,
        $exclusive = false,
        $auto_delete = true,
        $nowait = false,
        $arguments = array(),
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) = $this->protocolWriter->queueDeclare(
            $ticket,
            $queue,
            $passive,
            $durable,
            $exclusive,
            $auto_delete,
            $nowait,
            $arguments
        );

        $this->send_method_frame(array($class_id, $method_id), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->get_wait('queue.declare_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirms a queue definition
     *
     * @param AMQPReader $reader
     * @return string[]
     */
    protected function queue_declare_ok(AMQPReader $reader)
    {
        $queue = $reader->read_shortstr();
        $message_count = $reader->read_long();
        $consumer_count = $reader->read_long();

        return array($queue, $message_count, $consumer_count);
    }

    /**
     * Deletes a queue
     *
     * @param string $queue
     * @param bool $if_unused
     * @param bool $if_empty
     * @param bool $nowait
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed|null
     */
    public function queue_delete($queue = '', $if_unused = false, $if_empty = false, $nowait = false, $ticket = null)
    {
        $ticket = $this->getTicket($ticket);

        list($class_id, $method_id, $args) = $this->protocolWriter->queueDelete(
            $ticket,
            $queue,
            $if_unused,
            $if_empty,
            $nowait
        );

        $this->send_method_frame(array($class_id, $method_id), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->get_wait('queue.delete_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirms deletion of a queue
     *
     * @param AMQPReader $reader
     * @return int|string
     */
    protected function queue_delete_ok(AMQPReader $reader)
    {
        return $reader->read_long();
    }

    /**
     * Purges a queue
     *
     * @param string $queue
     * @param bool $nowait
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed|null
     */
    public function queue_purge($queue = '', $nowait = false, $ticket = null)
    {
        $ticket = $this->getTicket($ticket);
        list($class_id, $method_id, $args) = $this->protocolWriter->queuePurge($ticket, $queue, $nowait);

        $this->send_method_frame(array($class_id, $method_id), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->get_wait('queue.purge_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirms a queue purge
     *
     * @param AMQPReader $reader
     * @return int|string
     */
    protected function queue_purge_ok(AMQPReader $reader)
    {
        return $reader->read_long();
    }

    /**
     * Acknowledges one or more messages
     *
     * @param int $delivery_tag
     * @param bool $multiple
     */
    public function basic_ack($delivery_tag, $multiple = false)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->basicAck($delivery_tag, $multiple);
        $this->send_method_frame(array($class_id, $method_id), $args);
    }

    /**
     * Called when the server sends a basic.ack
     *
     * @param AMQPReader $reader
     * @throws AMQPRuntimeException
     */
    protected function basic_ack_from_server(AMQPReader $reader): void
    {
        $delivery_tag = $reader->read_longlong();
        $multiple = (bool) $reader->read_bit();

        if (!isset($this->published_messages[$delivery_tag])) {
            throw new AMQPRuntimeException(sprintf(
                'Server ack\'ed unknown delivery_tag "%s"',
                $delivery_tag
            ));
        }

        $this->internal_ack_handler($delivery_tag, $multiple, $this->ack_handler);
    }

    /**
     * Called when the server sends a basic.nack
     *
     * @param AMQPReader $reader
     * @throws AMQPRuntimeException
     */
    protected function basic_nack_from_server(AMQPReader $reader): void
    {
        $delivery_tag = $reader->read_longlong();
        $multiple = (bool) $reader->read_bit();

        if (!isset($this->published_messages[$delivery_tag])) {
            throw new AMQPRuntimeException(sprintf(
                'Server nack\'ed unknown delivery_tag "%s"',
                $delivery_tag
            ));
        }

        $this->internal_ack_handler($delivery_tag, $multiple, $this->nack_handler);
    }

    /**
     * Handles the deletion of messages from this->publishedMessages and dispatches them to the $handler
     *
     * @param int $delivery_tag
     * @param bool $multiple
     * @param callable $handler
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

    /**
     * @param AMQPMessage[] $messages
     * @param string $value
     * @return mixed
     */
    protected function get_keys_less_or_equal(array $messages, $value)
    {
        $value = (int) $value;
        $keys = array_reduce(
            array_keys($messages),
            /**
             * @param string $key
             */
            function ($keys, $key) use ($value) {
                if ($key <= $value) {
                    $keys[] = $key;
                }

                return $keys;
            },
            array()
        );

        return $keys;
    }

    /**
     * Rejects one or several received messages
     *
     * @param int $delivery_tag
     * @param bool $multiple
     * @param bool $requeue
     */
    public function basic_nack($delivery_tag, $multiple = false, $requeue = false)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->basicNack($delivery_tag, $multiple, $requeue);
        $this->send_method_frame(array($class_id, $method_id), $args);
    }

    /**
     * Ends a queue consumer
     *
     * @param string $consumer_tag
     * @param bool $nowait
     * @param bool $noreturn
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function basic_cancel($consumer_tag, $nowait = false, $noreturn = false)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->basicCancel($consumer_tag, $nowait);
        $this->send_method_frame(array($class_id, $method_id), $args);

        if ($nowait || $noreturn) {
            unset($this->callbacks[$consumer_tag]);
            return $consumer_tag;
        }

        return $this->wait(array(
            $this->waitHelper->get_wait('basic.cancel_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * @param AMQPReader $reader
     * @throws \PhpAmqpLib\Exception\AMQPBasicCancelException
     */
    protected function basic_cancel_from_server(AMQPReader $reader)
    {
        throw new AMQPBasicCancelException($reader->read_shortstr());
    }

    /**
     * Confirm a cancelled consumer
     *
     * @param AMQPReader $reader
     * @return string
     */
    protected function basic_cancel_ok(AMQPReader $reader): string
    {
        $consumerTag = $reader->read_shortstr();
        unset($this->callbacks[$consumerTag]);

        return $consumerTag;
    }

    /**
     * @return bool
     */
    public function is_consuming()
    {
        return !empty($this->callbacks);
    }

    /**
     * Start a queue consumer.
     * This method asks the server to start a "consumer", which is a transient request for messages
     * from a specific queue.
     * Consumers last as long as the channel they were declared on, or until the client cancels them.
     *
     * @link https://www.rabbitmq.com/amqp-0-9-1-reference.html#basic.consume
     *
     * @param string $queue
     * @param string $consumer_tag
     * @param bool $no_local
     * @param bool $no_ack
     * @param bool $exclusive
     * @param bool $nowait
     * @param callable|null $callback
     * @param int|null $ticket
     * @param \PhpAmqpLib\Wire\AMQPTable|array $arguments
     *
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @throws \InvalidArgumentException
     * @return string
     */
    public function basic_consume(
        $queue = '',
        $consumer_tag = '',
        $no_local = false,
        $no_ack = false,
        $exclusive = false,
        $nowait = false,
        $callback = null,
        $ticket = null,
        $arguments = array()
    ) {
        if (null !== $callback) {
            Assert::isCallable($callback);
        }
        if ($nowait && empty($consumer_tag)) {
            throw new \InvalidArgumentException('Cannot start consumer without consumer_tag and no-wait=true');
        }
        if (!empty($consumer_tag) && array_key_exists($consumer_tag, $this->callbacks)) {
            throw new \InvalidArgumentException('This consumer tag is already registered.');
        }

        $ticket = $this->getTicket($ticket);
        list($class_id, $method_id, $args) = $this->protocolWriter->basicConsume(
            $ticket,
            $queue,
            $consumer_tag,
            $no_local,
            $no_ack,
            $exclusive,
            $nowait,
            $this->protocolVersion === Wire\Constants091::VERSION ? $arguments : null
        );

        $this->send_method_frame(array($class_id, $method_id), $args);

        if (false === $nowait) {
            $consumer_tag = $this->wait(array(
                $this->waitHelper->get_wait('basic.consume_ok')
            ), false, $this->channel_rpc_timeout);
        }

        $this->callbacks[$consumer_tag] = $callback;

        return $consumer_tag;
    }

    /**
     * Confirms a new consumer
     *
     * @param AMQPReader $reader
     * @return string
     */
    protected function basic_consume_ok(AMQPReader $reader): string
    {
        return $reader->read_shortstr();
    }

    /**
     * Notifies the client of a consumer message
     *
     * @param AMQPReader $reader
     * @param AMQPMessage $message
     */
    protected function basic_deliver(AMQPReader $reader, AMQPMessage $message): void
    {
        $consumer_tag = $reader->read_shortstr();
        $delivery_tag = $reader->read_longlong();
        $redelivered = $reader->read_bit();
        $exchange = $reader->read_shortstr();
        $routing_key = $reader->read_shortstr();

        $message
            ->setChannel($this)
            ->setDeliveryInfo($delivery_tag, $redelivered, $exchange, $routing_key)
            ->setConsumerTag($consumer_tag);

        if (isset($this->callbacks[$consumer_tag])) {
            call_user_func($this->callbacks[$consumer_tag], $message);
        }
    }

    /**
     * Direct access to a queue if no message was available in the queue, return null
     *
     * @param string $queue
     * @param bool $no_ack
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return AMQPMessage|null
     */
    public function basic_get($queue = '', $no_ack = false, $ticket = null)
    {
        $ticket = $this->getTicket($ticket);
        list($class_id, $method_id, $args) = $this->protocolWriter->basicGet($ticket, $queue, $no_ack);

        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
            $this->waitHelper->get_wait('basic.get_ok'),
            $this->waitHelper->get_wait('basic.get_empty')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Indicates no messages available
     */
    protected function basic_get_empty()
    {
    }

    /**
     * Provides client with a message
     *
     * @param AMQPReader $reader
     * @param AMQPMessage $message
     * @return AMQPMessage
     */
    protected function basic_get_ok(AMQPReader $reader, AMQPMessage $message): AMQPMessage
    {
        $delivery_tag = $reader->read_longlong();
        $redelivered = $reader->read_bit();
        $exchange = $reader->read_shortstr();
        $routing_key = $reader->read_shortstr();
        $message_count = $reader->read_long();

        $message
            ->setChannel($this)
            ->setDeliveryInfo($delivery_tag, $redelivered, $exchange, $routing_key)
            ->setMessageCount($message_count);

        return $message;
    }

    /**
     * @param string $exchange
     * @param string $routing_key
     * @param bool $mandatory
     * @param bool $immediate
     * @param int $ticket
     * @return mixed
     */
    private function prePublish($exchange, $routing_key, $mandatory, $immediate, $ticket)
    {
        $cache_key = sprintf(
            '%s|%s|%s|%s|%s',
            $exchange,
            $routing_key,
            $mandatory,
            $immediate,
            $ticket
        );
        if (false === isset($this->publish_cache[$cache_key])) {
            $ticket = $this->getTicket($ticket);
            list($class_id, $method_id, $args) = $this->protocolWriter->basicPublish(
                $ticket,
                $exchange,
                $routing_key,
                $mandatory,
                $immediate
            );

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
     * Publishes a message
     *
     * @param AMQPMessage $msg
     * @param string $exchange
     * @param string $routing_key
     * @param bool $mandatory
     * @param bool $immediate
     * @param int|null $ticket
     * @throws AMQPChannelClosedException
     * @throws AMQPConnectionClosedException
     * @throws AMQPConnectionBlockedException
     */
    public function basic_publish(
        $msg,
        $exchange = '',
        $routing_key = '',
        $mandatory = false,
        $immediate = false,
        $ticket = null
    ) {
        $this->checkConnection();
        $pkt = new AMQPWriter();
        $pkt->write($this->prePublish($exchange, $routing_key, $mandatory, $immediate, $ticket));

        try {
            $this->connection->send_content(
                $this->channel_id,
                60,
                0,
                mb_strlen($msg->body, 'ASCII'),
                $msg->serialize_properties(),
                $msg->body,
                $pkt
            );
        } catch (AMQPConnectionClosedException $e) {
            $this->do_close();
            throw $e;
        }

        if ($this->next_delivery_tag > 0) {
            $this->published_messages[$this->next_delivery_tag] = $msg;
            $msg->setDeliveryInfo($this->next_delivery_tag, false, $exchange, $routing_key);
            $this->next_delivery_tag++;
        }
    }

    /**
     * @param AMQPMessage $message
     * @param string $exchange
     * @param string $routing_key
     * @param bool $mandatory
     * @param bool $immediate
     * @param int|null $ticket
     */
    public function batch_basic_publish(
        $message,
        $exchange = '',
        $routing_key = '',
        $mandatory = false,
        $immediate = false,
        $ticket = null
    ) {
        $this->batch_messages[] = [
            $message,
            $exchange,
            $routing_key,
            $mandatory,
            $immediate,
            $ticket
        ];
    }

    /**
     * Publish batch
     *
     * @return void
     * @throws AMQPChannelClosedException
     * @throws AMQPConnectionClosedException
     * @throws AMQPConnectionBlockedException
     */
    public function publish_batch()
    {
        if (empty($this->batch_messages)) {
            return;
        }

        $this->checkConnection();

        /** @var AMQPWriter $pkt */
        $pkt = new AMQPWriter();

        foreach ($this->batch_messages as $m) {
            /** @var AMQPMessage $msg */
            $msg = $m[0];

            $exchange = isset($m[1]) ? $m[1] : '';
            $routing_key = isset($m[2]) ? $m[2] : '';
            $mandatory = isset($m[3]) ? $m[3] : false;
            $immediate = isset($m[4]) ? $m[4] : false;
            $ticket = isset($m[5]) ? $m[5] : null;
            $pkt->write($this->prePublish($exchange, $routing_key, $mandatory, $immediate, $ticket));

            $this->connection->prepare_content(
                $this->channel_id,
                60,
                0,
                mb_strlen($msg->body, 'ASCII'),
                $msg->serialize_properties(),
                $msg->body,
                $pkt
            );

            if ($this->next_delivery_tag > 0) {
                $this->published_messages[$this->next_delivery_tag] = $msg;
                $this->next_delivery_tag++;
            }
        }

        $this->connection->write($pkt->getvalue());
        $this->batch_messages = array();
    }

    /**
     * Specifies QoS
     * 
     * See https://www.rabbitmq.com/consumer-prefetch.html#overview for details
     * 
     * @param int $prefetch_size Default is 0 (Alias for unlimited)
     * @param int $prefetch_count Default is 0 (Alias for unlimited)
     * @param bool $global Default is false, prefetch size and count are applied to each channel consumer separately
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function basic_qos($prefetch_size, $prefetch_count, $a_global)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->basicQos(
            $prefetch_size,
            $prefetch_count,
            $a_global
        );

        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
            $this->waitHelper->get_wait('basic.qos_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirms QoS request
     */
    protected function basic_qos_ok()
    {
    }

    /**
     * Redelivers unacknowledged messages
     *
     * @param bool $requeue
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function basic_recover($requeue = false)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->basicRecover($requeue);
        $this->send_method_frame(array($class_id, $method_id), $args);

        return $this->wait(array(
            $this->waitHelper->get_wait('basic.recover_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirm the requested recover
     */
    protected function basic_recover_ok()
    {
    }

    /**
     * Rejects an incoming message
     *
     * @param int $delivery_tag
     * @param bool $requeue
     */
    public function basic_reject($delivery_tag, $requeue)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->basicReject($delivery_tag, $requeue);
        $this->send_method_frame(array($class_id, $method_id), $args);
    }

    /**
     * Returns a failed message
     *
     * @param AMQPReader $reader
     * @param AMQPMessage $message
     */
    protected function basic_return(AMQPReader $reader, AMQPMessage $message)
    {
        $callback = $this->basic_return_callback;
        if (!is_callable($callback)) {
            $this->debug->debug_msg('Skipping unhandled basic_return message');
            return null;
        }

        $reply_code = $reader->read_short();
        $reply_text = $reader->read_shortstr();
        $exchange = $reader->read_shortstr();
        $routing_key = $reader->read_shortstr();

        call_user_func_array($callback, array(
            $reply_code,
            $reply_text,
            $exchange,
            $routing_key,
            $message,
        ));
    }

    /**
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function tx_commit()
    {
        $this->send_method_frame(array(90, 20));

        return $this->wait(array(
            $this->waitHelper->get_wait('tx.commit_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirms a successful commit
     */
    protected function tx_commit_ok()
    {
    }

    /**
     * Rollbacks the current transaction
     *
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function tx_rollback()
    {
        $this->send_method_frame(array(90, 30));

        return $this->wait(array(
            $this->waitHelper->get_wait('tx.rollback_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirms a successful rollback
     */
    protected function tx_rollback_ok()
    {
    }

    /**
     * Puts the channel into confirm mode
     * Beware that only non-transactional channels may be put into confirm mode and vice versa
     *
     * @param bool $nowait
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     */
    public function confirm_select($nowait = false)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->confirmSelect($nowait);

        $this->send_method_frame(array($class_id, $method_id), $args);

        if ($nowait) {
            return null;
        }

        $this->wait(array(
            $this->waitHelper->get_wait('confirm.select_ok')
        ), false, $this->channel_rpc_timeout);
        $this->next_delivery_tag = 1;
    }

    /**
     * Confirms a selection
     */
    public function confirm_select_ok()
    {
    }

    /**
     * Waits for pending acks and nacks from the server.
     * If there are no pending acks, the method returns immediately
     *
     * @param int|float $timeout Waits until $timeout value is reached
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function wait_for_pending_acks($timeout = 0)
    {
        $functions = array(
            $this->waitHelper->get_wait('basic.ack'),
            $this->waitHelper->get_wait('basic.nack'),
        );
        $timeout = max(0, $timeout);
        while (!empty($this->published_messages)) {
            $this->wait($functions, false, $timeout);
        }
    }

    /**
     * Waits for pending acks, nacks and returns from the server.
     * If there are no pending acks, the method returns immediately.
     *
     * @param int|float $timeout If set to value > 0 the method will wait at most $timeout seconds for pending acks.
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function wait_for_pending_acks_returns($timeout = 0)
    {
        $functions = array(
            $this->waitHelper->get_wait('basic.ack'),
            $this->waitHelper->get_wait('basic.nack'),
            $this->waitHelper->get_wait('basic.return'),
        );

        $timeout = max(0, $timeout);
        while (!empty($this->published_messages)) {
            $this->wait($functions, false, $timeout);
        }
    }

    /**
     * Selects standard transaction mode
     *
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function tx_select()
    {
        $this->send_method_frame(array(90, 10));

        return $this->wait(array(
            $this->waitHelper->get_wait('tx.select_ok')
        ), false, $this->channel_rpc_timeout);
    }

    /**
     * Confirms transaction mode
     */
    protected function tx_select_ok()
    {
    }

    /**
     * @param int|null $ticket
     * @return int
     */
    protected function getTicket($ticket)
    {
        return (null === $ticket) ? $this->default_ticket : $ticket;
    }

    /**
     * Helper method to get a particular method from $this->publishedMessages, removes it from the array and returns it.
     *
     * @param int $index
     * @return AMQPMessage
     */
    protected function get_and_unset_message($index)
    {
        $message = $this->published_messages[$index];
        unset($this->published_messages[$index]);

        return $message;
    }

    /**
     * Sets callback for basic_return
     *
     * @param  callable $callback
     * @throws \InvalidArgumentException if $callback is not callable
     */
    public function set_return_listener($callback)
    {
        Assert::isCallable($callback);
        $this->basic_return_callback = $callback;
    }

    /**
     * Sets a handler which called for any message nack'ed by the server, with the AMQPMessage as first argument.
     *
     * @param callable $callback
     * @throws \InvalidArgumentException
     */
    public function set_nack_handler($callback)
    {
        Assert::isCallable($callback);
        $this->nack_handler = $callback;
    }

    /**
     * Sets a handler which called for any message ack'ed by the server, with the AMQPMessage as first argument.
     *
     * @param callable $callback
     * @throws \InvalidArgumentException
     */
    public function set_ack_handler($callback)
    {
        Assert::isCallable($callback);
        $this->ack_handler = $callback;
    }

    /**
     * @throws AMQPChannelClosedException
     * @throws AMQPConnectionClosedException
     * @throws AMQPConnectionBlockedException
     */
    private function checkConnection()
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            throw new AMQPChannelClosedException('Channel connection is closed.');
        }
        if ($this->connection->isBlocked()) {
            throw new AMQPConnectionBlockedException();
        }
    }

    /**
     * Wait and process all incoming messages in an endless loop,
     * until connection exception or manual stop using self::stopConsume()
     *
     * @param float $maximumPoll Maximum time in seconds between read attempts
     * @throws \PhpAmqpLib\Exception\AMQPOutOfBoundsException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     * @throws \PhpAmqpLib\Exception\AMQPConnectionClosedException
     * @throws \ErrorException
     * @since 3.2.0
     */
    public function consume(float $maximumPoll = 10.0): void
    {
        $this->checkConnection();

        if ($this->stopConsume) {
            $this->stopConsume = false;
            return;
        }

        $timeout = $this->connection->getReadTimeout();
        $heartBeat = $this->connection->getHeartbeat();
        if ($heartBeat > 2) {
            $timeout = min($timeout, floor($heartBeat / 2));
        }
        $timeout = max(min($timeout, $maximumPoll), 1);
        while ($this->is_consuming() || !empty($this->method_queue)) {
            if ($this->stopConsume) {
                $this->stopConsume = false;
                return;
            }
            try {
                $this->wait(null, false, $timeout);
            } catch (AMQPTimeoutException $exception) {
                // something might be wrong, try to send heartbeat which involves select+write
                $this->connection->checkHeartBeat();
                continue;
            } catch (AMQPNoDataException $exception) {
                continue;
            }
        }
    }

    /**
     * Stop AMQPChannel::consume() loop. Useful for signal handlers and other interrupts.
     * @since 3.2.0
     */
    public function stopConsume()
    {
        $this->stopConsume = true;
    }
}

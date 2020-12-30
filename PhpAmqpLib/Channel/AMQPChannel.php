<?php

namespace PhpAmqpLib\Channel;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPBasicCancelException;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionBlockedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Helper\Assert;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire;
use PhpAmqpLib\Wire\AMQPReader;
use PhpAmqpLib\Wire\AMQPWriter;

class AMQPChannel extends AbstractChannel
{
    /**
     * @var callable[]
     * @internal Use is_consuming() to check if there is active callbacks
     */
    public $callbacks = array();

    /** @var bool Whether or not the channel has been "opened" */
    protected $isOpen = false;

    /** @var int */
    protected $defaultTicket = 0;

    /** @var bool */
    protected $active = true;

    /** @var array */
    protected $alerts = array();

    /** @var bool */
    protected $autoDecode;

    /**
     * These parameters will be passed to function in case of basic_return:
     *    param int $replyCode
     *    param string $replyText
     *    param string $exchange
     *    param string $routingKey
     *    param AMQPMessage $msg
     *
     * @var null|callable
     */
    protected $basicReturnCallback;

    /** @var array Used to keep track of the messages that are going to be batch published. */
    protected $batchMessages = array();

    /**
     * If the channel is in confirm_publish mode this array will store all published messages
     * until they get ack'ed or nack'ed
     *
     * @var AMQPMessage[]
     */
    private $publishedMessages = array();

    /** @var int */
    private $nextDeliveryTag = 0;

    /** @var null|callable */
    private $ackHandler;

    /** @var null|callable */
    private $nackHandler;

    /**
     * Circular buffer to speed up both basic_publish() and publish_batch().
     * Max size limited by $publish_cache_max_size.
     *
     * @var array
     * @see basicPublish()
     * @see publishBatch()
     */
    private $publishCache = array();

    /**
     * Maximal size of $publish_cache
     *
     * @var int
     */
    private $publishCacheMaxSize = 100;

    /**
     * Maximum time to wait for operations on this channel, in seconds.
     *
     * @var float $channelRpcTimeout
     */
    protected $channelRpcTimeout;

    /**
     * @param AbstractConnection $connection
     * @param int|null           $channelId
     * @param bool               $autoDecode
     * @param int|float          $channelRpcTimeout
     *
     * @throws \Exception
     */
    public function __construct($connection, $channelId = null, $autoDecode = true, $channelRpcTimeout = 0)
    {
        if ($channelId == null) {
            $channelId = $connection->getFreeChannelId();
        }

        parent::__construct($connection, $channelId);

        $this->debug->debugMsg('using channel_id: ' . $channelId);

        $this->autoDecode = $autoDecode;
        $this->channelRpcTimeout = $channelRpcTimeout;

        try {
            $this->xOpen();
        } catch (\Exception $e) {
            $this->close();
            throw $e;
        }
    }

    /**
     * @return bool
     */
    public function isOpen()
    {
        return $this->isOpen;
    }

    /**
     * Tear down this object, after we've agreed to close with the server.
     */
    protected function doClose()
    {
        if ($this->channelId !== null) {
            unset($this->connection->channels[$this->channelId]);
        }
        $this->channelId = $this->connection = null;
        $this->isOpen = false;
        $this->callbacks = [];
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
    protected function channelAlert($reader)
    {
        $replyCode = $reader->readShort();
        $replyText = $reader->readShortstr();
        $details = $reader->readTable();
        array_push($this->alerts, array($replyCode, $replyText, $details));
    }

    /**
     * Request a channel close
     *
     * @param int    $replyCode
     * @param string $replyText
     * @param array  $methodSig
     *
     * @return mixed
     *@throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     */
    public function close($replyCode = 0, $replyText = '', $methodSig = array(0, 0))
    {
        $this->callbacks = array();
        if ($this->isOpen === false || $this->connection === null) {
            $this->doClose();

            return null; // already closed
        }
        list($classId, $methodId, $args) = $this->protocolWriter->channelClose(
            $replyCode,
            $replyText,
            $methodSig[0],
            $methodSig[1]
        );

        try {
            $this->sendMethodFrame(array($classId, $methodId), $args);
        } catch (\Exception $e) {
            $this->doClose();

            throw $e;
        }

        return $this->wait(array(
            $this->waitHelper->getWait('channel.close_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * @param AMQPReader $reader
     * @throws \PhpAmqpLib\Exception\AMQPProtocolChannelException
     */
    protected function channelClose($reader)
    {
        $replyCode = $reader->readShort();
        $replyText = $reader->readShortstr();
        $classId = $reader->readShort();
        $methodId = $reader->readShort();

        $this->sendMethodFrame(array(20, 41));
        $this->doClose();

        throw new AMQPProtocolChannelException($replyCode, $replyText, array($classId, $methodId));
    }

    /**
     * Confirm a channel close
     * Alias of AMQPChannel::do_close()
     */
    protected function channelCloseOk()
    {
        $this->doClose();
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
        list($classId, $methodId, $args) = $this->protocolWriter->channelFlow($active);
        $this->sendMethodFrame(array($classId, $methodId), $args);

        return $this->wait(array(
            $this->waitHelper->getWait('channel.flow_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * @param AMQPReader $reader
     */
    protected function channelFlow($reader)
    {
        $this->active = $reader->readBit();
        $this->xFlowOk($this->active);
    }

    /**
     * @param bool $active
     */
    protected function xFlowOk($active)
    {
        list($classId, $methodId, $args) = $this->protocolWriter->channelFlow($active);
        $this->sendMethodFrame(array($classId, $methodId), $args);
    }

    /**
     * @param AMQPReader $reader
     * @return bool
     */
    protected function channelFlowOk($reader)
    {
        return $reader->readBit();
    }

    /**
     * @param string $outOfBand
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    protected function xOpen($outOfBand = '')
    {
        if ($this->isOpen) {
            return null;
        }

        list($classId, $methodId, $args) = $this->protocolWriter->channelOpen($outOfBand);
        $this->sendMethodFrame(array($classId, $methodId), $args);

        return $this->wait(array(
            $this->waitHelper->getWait('channel.open_ok')
        ), false, $this->channelRpcTimeout);
    }

    protected function channelOpenOk()
    {
        $this->isOpen = true;

        $this->debug->debugMsg('Channel open');
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
    public function accessRequest(
        $realm,
        $exclusive = false,
        $passive = false,
        $active = false,
        $write = false,
        $read = false
    ) {
        list($classId, $methodId, $args) = $this->protocolWriter->accessRequest(
            $realm,
            $exclusive,
            $passive,
            $active,
            $write,
            $read
        );

        $this->sendMethodFrame(array($classId, $methodId), $args);

        return $this->wait(array(
            $this->waitHelper->getWait('access.request_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Grants access to server resources
     *
     * @param AMQPReader $reader
     * @return string
     */
    protected function accessRequestOk($reader)
    {
        $this->defaultTicket = $reader->readShort();

        return $this->defaultTicket;
    }

    /**
     * Declares exchange
     *
     * @param string $exchange
     * @param string $type
     * @param bool $passive
     * @param bool $durable
     * @param bool $autoDelete
     * @param bool $internal
     * @param bool $nowait
     * @param array $arguments
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed|null
     */
    public function exchangeDeclare(
        $exchange,
        $type,
        $passive = false,
        $durable = false,
        $autoDelete = true,
        $internal = false,
        $nowait = false,
        $arguments = array(),
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);

        list($classId, $methodId, $args) = $this->protocolWriter->exchangeDeclare(
            $ticket,
            $exchange,
            $type,
            $passive,
            $durable,
            $autoDelete,
            $internal,
            $nowait,
            $arguments
        );

        $this->sendMethodFrame(array($classId, $methodId), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->getWait('exchange.declare_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirms an exchange declaration
     */
    protected function exchangeDeclareOk()
    {
    }

    /**
     * Deletes an exchange
     *
     * @param string $exchange
     * @param bool $ifUnused
     * @param bool $nowait
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed|null
     */
    public function exchangeDelete(
        $exchange,
        $ifUnused = false,
        $nowait = false,
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);
        list($classId, $methodId, $args) = $this->protocolWriter->exchangeDelete(
            $ticket,
            $exchange,
            $ifUnused,
            $nowait
        );

        $this->sendMethodFrame(array($classId, $methodId), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->getWait('exchange.delete_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirms deletion of an exchange
     */
    protected function exchangeDeleteOk()
    {
    }

    /**
     * Binds dest exchange to source exchange
     *
     * @param string $destination
     * @param string $source
     * @param string $routingKey
     * @param bool $nowait
     * @param array $arguments
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed|null
     */
    public function exchangeBind(
        $destination,
        $source,
        $routingKey = '',
        $nowait = false,
        $arguments = array(),
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);

        list($classId, $methodId, $args) = $this->protocolWriter->exchangeBind(
            $ticket,
            $destination,
            $source,
            $routingKey,
            $nowait,
            $arguments
        );

        $this->sendMethodFrame(array($classId, $methodId), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->getWait('exchange.bind_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirms bind successful
     */
    protected function exchangeBindOk()
    {
    }

    /**
     * Unbinds dest exchange from source exchange
     *
     * @param string $destination
     * @param string $source
     * @param string $routingKey
     * @param bool $nowait
     * @param array $arguments
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function exchangeUnbind(
        $destination,
        $source,
        $routingKey = '',
        $nowait = false,
        $arguments = array(),
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);

        list($classId, $methodId, $args) = $this->protocolWriter->exchangeUnbind(
            $ticket,
            $destination,
            $source,
            $routingKey,
            $nowait,
            $arguments
        );

        $this->sendMethodFrame(array($classId, $methodId), $args);

        return $this->wait(array(
            $this->waitHelper->getWait('exchange.unbind_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirms unbind successful
     */
    protected function exchangeUnbindOk()
    {
    }

    /**
     * Binds queue to an exchange
     *
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param bool $nowait
     * @param array $arguments
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed|null
     */
    public function queueBind(
        $queue,
        $exchange,
        $routingKey = '',
        $nowait = false,
        $arguments = array(),
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);

        list($classId, $methodId, $args) = $this->protocolWriter->queueBind(
            $ticket,
            $queue,
            $exchange,
            $routingKey,
            $nowait,
            $arguments
        );

        $this->sendMethodFrame(array($classId, $methodId), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->getWait('queue.bind_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirms bind successful
     */
    protected function queueBindOk()
    {
    }

    /**
     * Unbind queue from an exchange
     *
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param array $arguments
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function queueUnbind(
        $queue,
        $exchange,
        $routingKey = '',
        $arguments = array(),
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);

        list($classId, $methodId, $args) = $this->protocolWriter->queueUnbind(
            $ticket,
            $queue,
            $exchange,
            $routingKey,
            $arguments
        );

        $this->sendMethodFrame(array($classId, $methodId), $args);

        return $this->wait(array(
            $this->waitHelper->getWait('queue.unbind_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirms unbind successful
     */
    protected function queueUnbindOk()
    {
    }

    /**
     * Declares queue, creates if needed
     *
     * @param string $queue
     * @param bool $passive
     * @param bool $durable
     * @param bool $exclusive
     * @param bool $autoDelete
     * @param bool $nowait
     * @param array|\PhpAmqpLib\Wire\AMQPTable $arguments
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return array|null
     */
    public function queueDeclare(
        $queue = '',
        $passive = false,
        $durable = false,
        $exclusive = false,
        $autoDelete = true,
        $nowait = false,
        $arguments = array(),
        $ticket = null
    ) {
        $ticket = $this->getTicket($ticket);

        list($classId, $methodId, $args) = $this->protocolWriter->queueDeclare(
            $ticket,
            $queue,
            $passive,
            $durable,
            $exclusive,
            $autoDelete,
            $nowait,
            $arguments
        );

        $this->sendMethodFrame(array($classId, $methodId), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->getWait('queue.declare_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirms a queue definition
     *
     * @param AMQPReader $reader
     * @return string[]
     */
    protected function queueDeclareOk($reader)
    {
        $queue = $reader->readShortstr();
        $messageCount = $reader->readLong();
        $consumerCount = $reader->readLong();

        return array($queue, $messageCount, $consumerCount);
    }

    /**
     * Deletes a queue
     *
     * @param string $queue
     * @param bool $ifUnused
     * @param bool $ifEmpty
     * @param bool $nowait
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed|null
     */
    public function queueDelete($queue = '', $ifUnused = false, $ifEmpty = false, $nowait = false, $ticket = null)
    {
        $ticket = $this->getTicket($ticket);

        list($classId, $methodId, $args) = $this->protocolWriter->queueDelete(
            $ticket,
            $queue,
            $ifUnused,
            $ifEmpty,
            $nowait
        );

        $this->sendMethodFrame(array($classId, $methodId), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->getWait('queue.delete_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirms deletion of a queue
     *
     * @param AMQPReader $reader
     * @return string
     */
    protected function queueDeleteOk($reader)
    {
        return $reader->readLong();
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
    public function queuePurge($queue = '', $nowait = false, $ticket = null)
    {
        $ticket = $this->getTicket($ticket);
        list($classId, $methodId, $args) = $this->protocolWriter->queuePurge($ticket, $queue, $nowait);

        $this->sendMethodFrame(array($classId, $methodId), $args);

        if ($nowait) {
            return null;
        }

        return $this->wait(array(
            $this->waitHelper->getWait('queue.purge_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirms a queue purge
     *
     * @param AMQPReader $reader
     * @return string
     */
    protected function queuePurgeOk($reader)
    {
        return $reader->readLong();
    }

    /**
     * Acknowledges one or more messages
     *
     * @param int $deliveryTag
     * @param bool $multiple
     */
    public function basicAck($deliveryTag, $multiple = false)
    {
        list($classId, $methodId, $args) = $this->protocolWriter->basicAck($deliveryTag, $multiple);
        $this->sendMethodFrame(array($classId, $methodId), $args);
    }

    /**
     * Called when the server sends a basic.ack
     *
     * @param AMQPReader $reader
     * @throws AMQPRuntimeException
     */
    protected function basicAckFromServer(AMQPReader $reader)
    {
        $deliveryTag = $reader->readLonglong();
        $multiple = (bool) $reader->readBit();

        if (!isset($this->publishedMessages[$deliveryTag])) {
            throw new AMQPRuntimeException(sprintf(
                'Server ack\'ed unknown delivery_tag "%s"',
                $deliveryTag
            ));
        }

        $this->internalAckHandler($deliveryTag, $multiple, $this->ackHandler);
    }

    /**
     * Called when the server sends a basic.nack
     *
     * @param AMQPReader $reader
     * @throws AMQPRuntimeException
     */
    protected function basicNackFromServer($reader)
    {
        $deliveryTag = $reader->readLonglong();
        $multiple = (bool) $reader->readBit();

        if (!isset($this->publishedMessages[$deliveryTag])) {
            throw new AMQPRuntimeException(sprintf(
                'Server nack\'ed unknown delivery_tag "%s"',
                $deliveryTag
            ));
        }

        $this->internalAckHandler($deliveryTag, $multiple, $this->nackHandler);
    }

    /**
     * Handles the deletion of messages from this->publishedMessages and dispatches them to the $handler
     *
     * @param int $deliveryTag
     * @param bool $multiple
     * @param callable $handler
     */
    protected function internalAckHandler($deliveryTag, $multiple, $handler)
    {
        if ($multiple) {
            $keys = $this->getKeysLessOrEqual($this->publishedMessages, $deliveryTag);

            foreach ($keys as $key) {
                $this->internalAckHandler($key, false, $handler);
            }
        } else {
            $message = $this->getAndUnsetMessage($deliveryTag);
            $this->dispatchToHandler($handler, array($message));
        }
    }

    /**
     * @param AMQPMessage[] $messages
     * @param string $value
     * @return mixed
     */
    protected function getKeysLessOrEqual(array $messages, $value)
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
     * @param int $deliveryTag
     * @param bool $multiple
     * @param bool $requeue
     */
    public function basicNack($deliveryTag, $multiple = false, $requeue = false)
    {
        list($classId, $methodId, $args) = $this->protocolWriter->basicNack($deliveryTag, $multiple, $requeue);
        $this->sendMethodFrame(array($classId, $methodId), $args);
    }

    /**
     * Ends a queue consumer
     *
     * @param string $consumerTag
     * @param bool $nowait
     * @param bool $noreturn
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function basicCancel($consumerTag, $nowait = false, $noreturn = false)
    {
        list($classId, $methodId, $args) = $this->protocolWriter->basicCancel($consumerTag, $nowait);
        $this->sendMethodFrame(array($classId, $methodId), $args);

        if ($nowait || $noreturn) {
            unset($this->callbacks[$consumerTag]);
            return $consumerTag;
        }

        return $this->wait(array(
            $this->waitHelper->getWait('basic.cancel_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * @param AMQPReader $reader
     * @throws \PhpAmqpLib\Exception\AMQPBasicCancelException
     */
    protected function basicCancelFromServer(AMQPReader $reader)
    {
        throw new AMQPBasicCancelException($reader->readShortstr());
    }

    /**
     * Confirm a cancelled consumer
     *
     * @param AMQPReader $reader
     * @return string
     */
    protected function basicCancelOk($reader)
    {
        $consumerTag = $reader->readShortstr();
        unset($this->callbacks[$consumerTag]);

        return $consumerTag;
    }

    /**
     * @return bool
     */
    public function isConsuming()
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
     * @param string $consumerTag
     * @param bool $noLocal
     * @param bool $noAck
     * @param bool $exclusive
     * @param bool $nowait
     * @param callable|null $callback
     * @param int|null $ticket
     * @param array $arguments
     *
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @throws \InvalidArgumentException
     * @return string
     */
    public function basicConsume(
        $queue = '',
        $consumerTag = '',
        $noLocal = false,
        $noAck = false,
        $exclusive = false,
        $nowait = false,
        $callback = null,
        $ticket = null,
        $arguments = array()
    ) {
        if (null !== $callback) {
            Assert::isCallable($callback);
        }
        if ($nowait && empty($consumerTag)) {
            throw new \InvalidArgumentException('Cannot start consumer without consumer_tag and no-wait=true');
        }
        if (!empty($consumerTag) && array_key_exists($consumerTag, $this->callbacks)) {
            throw new \InvalidArgumentException('This consumer tag is already registered.');
        }

        $ticket = $this->getTicket($ticket);
        list($classId, $methodId, $args) = $this->protocolWriter->basicConsume(
            $ticket,
            $queue,
            $consumerTag,
            $noLocal,
            $noAck,
            $exclusive,
            $nowait,
            $this->protocolVersion === Wire\Constants091::VERSION ? $arguments : null
        );

        $this->sendMethodFrame(array($classId, $methodId), $args);

        if (false === $nowait) {
            $consumerTag = $this->wait(array(
                $this->waitHelper->getWait('basic.consume_ok')
            ), false, $this->channelRpcTimeout);
        }

        $this->callbacks[$consumerTag] = $callback;

        return $consumerTag;
    }

    /**
     * Confirms a new consumer
     *
     * @param AMQPReader $reader
     * @return string
     */
    protected function basicConsumeOk($reader)
    {
        return $reader->readShortstr();
    }

    /**
     * Notifies the client of a consumer message
     *
     * @param AMQPReader $reader
     * @param AMQPMessage $message
     */
    protected function basicDeliver($reader, $message)
    {
        $consumerTag = $reader->readShortstr();
        $deliveryTag = $reader->readLonglong();
        $redelivered = $reader->readBit();
        $exchange = $reader->readShortstr();
        $routingKey = $reader->readShortstr();

        $message
            ->setChannel($this)
            ->setDeliveryInfo($deliveryTag, $redelivered, $exchange, $routingKey)
            ->setConsumerTag($consumerTag);

        if (isset($this->callbacks[$consumerTag])) {
            call_user_func($this->callbacks[$consumerTag], $message);
        }
    }

    /**
     * Direct access to a queue if no message was available in the queue, return null
     *
     * @param string $queue
     * @param bool $noAck
     * @param int|null $ticket
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return AMQPMessage|null
     */
    public function basicGet($queue = '', $noAck = false, $ticket = null)
    {
        $ticket = $this->getTicket($ticket);
        list($classId, $methodId, $args) = $this->protocolWriter->basicGet($ticket, $queue, $noAck);

        $this->sendMethodFrame(array($classId, $methodId), $args);

        return $this->wait(array(
            $this->waitHelper->getWait('basic.get_ok'),
            $this->waitHelper->getWait('basic.get_empty')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Indicates no messages available
     */
    protected function basicGetEmpty()
    {
    }

    /**
     * Provides client with a message
     *
     * @param AMQPReader $reader
     * @param AMQPMessage $message
     * @return AMQPMessage
     */
    protected function basicGetOk($reader, $message)
    {
        $deliveryTag = $reader->readLonglong();
        $redelivered = $reader->readBit();
        $exchange = $reader->readShortstr();
        $routingKey = $reader->readShortstr();
        $messageCount = $reader->readLong();

        $message
            ->setChannel($this)
            ->setDeliveryInfo($deliveryTag, $redelivered, $exchange, $routingKey)
            ->setMessageCount($messageCount);

        return $message;
    }

    /**
     * @param string $exchange
     * @param string $routingKey
     * @param bool $mandatory
     * @param bool $immediate
     * @param int $ticket
     * @return mixed
     */
    private function prePublish($exchange, $routingKey, $mandatory, $immediate, $ticket)
    {
        $cacheKey = sprintf(
            '%s|%s|%s|%s|%s',
            $exchange,
            $routingKey,
            $mandatory,
            $immediate,
            $ticket
        );
        if (false === isset($this->publishCache[$cacheKey])) {
            $ticket = $this->getTicket($ticket);
            list($classId, $methodId, $args) = $this->protocolWriter->basicPublish(
                $ticket,
                $exchange,
                $routingKey,
                $mandatory,
                $immediate
            );

            $pkt = $this->prepareMethodFrame(array($classId, $methodId), $args);
            $this->publishCache[$cacheKey] = $pkt->getvalue();
            if (count($this->publishCache) > $this->publishCacheMaxSize) {
                reset($this->publishCache);
                $oldKey = key($this->publishCache);
                unset($this->publishCache[$oldKey]);
            }
        }

        return $this->publishCache[$cacheKey];
    }

    /**
     * Publishes a message
     *
     * @param AMQPMessage $msg
     * @param string $exchange
     * @param string $routingKey
     * @param bool $mandatory
     * @param bool $immediate
     * @param int|null $ticket
     * @throws AMQPChannelClosedException
     * @throws AMQPConnectionClosedException
     * @throws AMQPConnectionBlockedException
     */
    public function basicPublish(
        $msg,
        $exchange = '',
        $routingKey = '',
        $mandatory = false,
        $immediate = false,
        $ticket = null
    ) {
        $this->checkConnection();
        $pkt = new AMQPWriter();
        $pkt->write($this->prePublish($exchange, $routingKey, $mandatory, $immediate, $ticket));

        try {
            $this->connection->sendContent(
                $this->channelId,
                60,
                0,
                mb_strlen($msg->body, 'ASCII'),
                $msg->serializeProperties(),
                $msg->body,
                $pkt
            );
        } catch (AMQPConnectionClosedException $e) {
            $this->doClose();
            throw $e;
        }

        if ($this->nextDeliveryTag > 0) {
            $this->publishedMessages[$this->nextDeliveryTag] = $msg;
            $msg->setDeliveryInfo($this->nextDeliveryTag, false, $exchange, $routingKey);
            $this->nextDeliveryTag++;
        }
    }

    /**
     * @param AMQPMessage $message
     * @param string $exchange
     * @param string $routingKey
     * @param bool $mandatory
     * @param bool $immediate
     * @param int|null $ticket
     */
    public function batchBasicPublish(
        $message,
        $exchange = '',
        $routingKey = '',
        $mandatory = false,
        $immediate = false,
        $ticket = null
    ) {
        $this->batchMessages[] = [
            $message,
            $exchange,
            $routingKey,
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
    public function publishBatch()
    {
        if (empty($this->batchMessages)) {
            return;
        }

        /** @var AMQPWriter $pkt */
        $pkt = new AMQPWriter();

        foreach ($this->batchMessages as $m) {
            /** @var AMQPMessage $msg */
            $msg = $m[0];

            $exchange = isset($m[1]) ? $m[1] : '';
            $routingKey = isset($m[2]) ? $m[2] : '';
            $mandatory = isset($m[3]) ? $m[3] : false;
            $immediate = isset($m[4]) ? $m[4] : false;
            $ticket = isset($m[5]) ? $m[5] : null;
            $pkt->write($this->prePublish($exchange, $routingKey, $mandatory, $immediate, $ticket));

            $this->connection->prepareContent(
                $this->channelId,
                60,
                0,
                mb_strlen($msg->body, 'ASCII'),
                $msg->serializeProperties(),
                $msg->body,
                $pkt
            );

            if ($this->nextDeliveryTag > 0) {
                $this->publishedMessages[$this->nextDeliveryTag] = $msg;
                $this->nextDeliveryTag++;
            }
        }

        $this->checkConnection();
        $this->connection->write($pkt->getvalue());
        $this->batchMessages = array();
    }

    /**
     * Specifies QoS
     *
     * @param int $prefetchSize
     * @param int $prefetchCount
     * @param bool $aGlobal
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function basicQos($prefetchSize, $prefetchCount, $aGlobal)
    {
        list($classId, $methodId, $args) = $this->protocolWriter->basicQos(
            $prefetchSize,
            $prefetchCount,
            $aGlobal
        );

        $this->sendMethodFrame(array($classId, $methodId), $args);

        return $this->wait(array(
            $this->waitHelper->getWait('basic.qos_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirms QoS request
     */
    protected function basicQosOk()
    {
    }

    /**
     * Redelivers unacknowledged messages
     *
     * @param bool $requeue
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function basicRecover($requeue = false)
    {
        list($classId, $methodId, $args) = $this->protocolWriter->basicRecover($requeue);
        $this->sendMethodFrame(array($classId, $methodId), $args);

        return $this->wait(array(
            $this->waitHelper->getWait('basic.recover_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirm the requested recover
     */
    protected function basicRecoverOk()
    {
    }

    /**
     * Rejects an incoming message
     *
     * @param int $deliveryTag
     * @param bool $requeue
     */
    public function basicReject($deliveryTag, $requeue)
    {
        list($classId, $methodId, $args) = $this->protocolWriter->basicReject($deliveryTag, $requeue);
        $this->sendMethodFrame(array($classId, $methodId), $args);
    }

    /**
     * Returns a failed message
     *
     * @param AMQPReader $reader
     * @param AMQPMessage $message
     */
    protected function basicReturn($reader, $message)
    {
        $callback = $this->basicReturnCallback;
        if (!is_callable($callback)) {
            $this->debug->debugMsg('Skipping unhandled basic_return message');
            return null;
        }

        $replyCode = $reader->readShort();
        $replyText = $reader->readShortstr();
        $exchange = $reader->readShortstr();
        $routingKey = $reader->readShortstr();

        call_user_func_array($callback, array(
            $replyCode,
            $replyText,
            $exchange,
            $routingKey,
            $message,
        ));
    }

    /**
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function txCommit()
    {
        $this->sendMethodFrame(array(90, 20));

        return $this->wait(array(
            $this->waitHelper->getWait('tx.commit_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirms a successful commit
     */
    protected function txCommitOk()
    {
    }

    /**
     * Rollbacks the current transaction
     *
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function txRollback()
    {
        $this->sendMethodFrame(array(90, 30));

        return $this->wait(array(
            $this->waitHelper->getWait('tx.rollback_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirms a successful rollback
     */
    protected function txRollbackOk()
    {
    }

    /**
     * Puts the channel into confirm mode
     * Beware that only non-transactional channels may be put into confirm mode and vice versa
     *
     * @param bool $nowait
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     */
    public function confirmSelect($nowait = false)
    {
        list($classId, $methodId, $args) = $this->protocolWriter->confirmSelect($nowait);

        $this->sendMethodFrame(array($classId, $methodId), $args);

        if ($nowait) {
            return null;
        }

        $this->wait(array(
            $this->waitHelper->getWait('confirm.select_ok')
        ), false, $this->channelRpcTimeout);
        $this->nextDeliveryTag = 1;
    }

    /**
     * Confirms a selection
     */
    public function confirmSelectOk()
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
    public function waitForPendingAcks($timeout = 0)
    {
        $functions = array(
            $this->waitHelper->getWait('basic.ack'),
            $this->waitHelper->getWait('basic.nack'),
        );
        $timeout = max(0, $timeout);
        while (!empty($this->publishedMessages)) {
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
    public function waitForPendingAcksReturns($timeout = 0)
    {
        $functions = array(
            $this->waitHelper->getWait('basic.ack'),
            $this->waitHelper->getWait('basic.nack'),
            $this->waitHelper->getWait('basic.return'),
        );

        $timeout = max(0, $timeout);
        while (!empty($this->publishedMessages)) {
            $this->wait($functions, false, $timeout);
        }
    }

    /**
     * Selects standard transaction mode
     *
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     * @return mixed
     */
    public function txSelect()
    {
        $this->sendMethodFrame(array(90, 10));

        return $this->wait(array(
            $this->waitHelper->getWait('tx.select_ok')
        ), false, $this->channelRpcTimeout);
    }

    /**
     * Confirms transaction mode
     */
    protected function txSelectOk()
    {
    }

    /**
     * @param int|null $ticket
     * @return int
     */
    protected function getTicket($ticket)
    {
        return (null === $ticket) ? $this->defaultTicket : $ticket;
    }

    /**
     * Helper method to get a particular method from $this->publishedMessages, removes it from the array and returns it.
     *
     * @param int $index
     * @return AMQPMessage
     */
    protected function getAndUnsetMessage($index)
    {
        $message = $this->publishedMessages[$index];
        unset($this->publishedMessages[$index]);

        return $message;
    }

    /**
     * Sets callback for basic_return
     *
     * @param  callable $callback
     * @throws \InvalidArgumentException if $callback is not callable
     */
    public function setReturnListener($callback)
    {
        Assert::isCallable($callback);
        $this->basicReturnCallback = $callback;
    }

    /**
     * Sets a handler which called for any message nack'ed by the server, with the AMQPMessage as first argument.
     *
     * @param callable $callback
     * @throws \InvalidArgumentException
     */
    public function setNackHandler($callback)
    {
        Assert::isCallable($callback);
        $this->nackHandler = $callback;
    }

    /**
     * Sets a handler which called for any message ack'ed by the server, with the AMQPMessage as first argument.
     *
     * @param callable $callback
     * @throws \InvalidArgumentException
     */
    public function setAckHandler($callback)
    {
        Assert::isCallable($callback);
        $this->ackHandler = $callback;
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
}

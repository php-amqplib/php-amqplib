<?php

namespace PhpAmqpLib\Interop;

use Interop\Amqp\AmqpConsumer as InteropAmqpConsumer;
use Interop\Amqp\AmqpMessage as InteropAmqpMessage;
use Interop\Amqp\AmqpQueue as InteropAmqpQueue;
use Interop\Amqp\Impl\AmqpMessage;
use Interop\Queue\Exception;
use Interop\Queue\InvalidMessageException;
use Interop\Queue\PsrMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage as LibAMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final class AmqpConsumer implements InteropAmqpConsumer
{
    /**
     * @var AMQPChannel
     */
    private $channel;

    /**
     * @var InteropAmqpQueue
     */
    private $queue;

    /**
     * @var Buffer
     */
    private $buffer;

    /**
     * @var bool
     */
    private $isInit;

    /**
     * @var string
     */
    private $receiveMethod;

    /**
     * @var int
     */
    private $flags;

    /**
     * @var string
     */
    private $consumerTag;

    /**
     * @param AMQPChannel      $channel
     * @param InteropAmqpQueue $queue
     * @param Buffer           $buffer
     * @param string           $receiveMethod
     */
    public function __construct(AMQPChannel $channel, InteropAmqpQueue $queue, Buffer $buffer, $receiveMethod)
    {
        $this->channel = $channel;
        $this->queue = $queue;
        $this->buffer = $buffer;
        $this->receiveMethod = $receiveMethod;
        $this->flags = self::FLAG_NOPARAM;

        $this->isInit = false;
    }

    /**
     * {@inheritdoc}
     */
    public function setConsumerTag($consumerTag)
    {
        if ($this->isInit) {
            throw new Exception('Consumer tag is not mutable after it has been subscribed to broker');
        }

        $this->consumerTag = $consumerTag;
    }

    /**
     * {@inheritdoc}
     */
    public function getConsumerTag()
    {
        return $this->consumerTag;
    }

    /**
     * {@inheritdoc}
     */
    public function clearFlags()
    {
        $this->flags = self::FLAG_NOPARAM;
    }

    /**
     * {@inheritdoc}
     */
    public function addFlag($flag)
    {
        $this->flags |= $flag;
    }

    /**
     * {@inheritdoc}
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * {@inheritdoc}
     */
    public function setFlags($flags)
    {
        $this->flags = $flags;
    }

    /**
     * @return InteropAmqpQueue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * {@inheritdoc}
     *
     * @return InteropAmqpMessage|null
     */
    public function receive($timeout = 0)
    {
        if ('basic_get' == $this->receiveMethod) {
            return $this->receiveBasicGet($timeout);
        }

        if ('basic_consume' == $this->receiveMethod) {
            return $this->receiveBasicConsume($timeout);
        }

        throw new \LogicException('The "receiveMethod" is not supported');
    }

    /**
     * @return InteropAmqpMessage|null
     */
    public function receiveNoWait()
    {
        if ($message = $this->channel->basic_get($this->queue->getQueueName(), (bool) ($this->getFlags() & InteropAmqpConsumer::FLAG_NOACK))) {
            return $this->convertMessage($message);
        }
    }

    /**
     * @param InteropAmqpMessage $message
     */
    public function acknowledge(PsrMessage $message)
    {
        InvalidMessageException::assertMessageInstanceOf($message, InteropAmqpMessage::class);

        $this->channel->basic_ack($message->getDeliveryTag());
    }

    /**
     * @param InteropAmqpMessage $message
     * @param bool               $requeue
     */
    public function reject(PsrMessage $message, $requeue = false)
    {
        InvalidMessageException::assertMessageInstanceOf($message, InteropAmqpMessage::class);

        $this->channel->basic_reject($message->getDeliveryTag(), $requeue);
    }

    /**
     * @param LibAMQPMessage $amqpMessage
     *
     * @return InteropAmqpMessage
     */
    private function convertMessage(LibAMQPMessage $amqpMessage)
    {
        $headers = new AMQPTable($amqpMessage->get_properties());
        $headers = $headers->getNativeData();

        $properties = [];
        if (isset($headers['application_headers'])) {
            $properties = $headers['application_headers'];
        }
        unset($headers['application_headers']);

        $message = new AmqpMessage($amqpMessage->getBody(), $properties, $headers);
        $message->setDeliveryTag($amqpMessage->delivery_info['delivery_tag']);
        $message->setRedelivered($amqpMessage->delivery_info['redelivered']);

        return $message;
    }

    /**
     * @param int $timeout
     *
     * @return InteropAmqpMessage|null
     */
    private function receiveBasicGet($timeout)
    {
        $end = microtime(true) + ($timeout / 1000);

        while (0 === $timeout || microtime(true) < $end) {
            if ($message = $this->receiveNoWait()) {
                return $message;
            }

            usleep(100000); //100ms
        }
    }

    /**
     * @param int $timeout
     *
     * @return InteropAmqpMessage|null
     */
    private function receiveBasicConsume($timeout)
    {
        if (false === $this->isInit) {
            $callback = function (LibAMQPMessage $message) {
                $receivedMessage = $this->convertMessage($message);
                $receivedMessage->setConsumerTag($message->delivery_info['consumer_tag']);

                $this->buffer->push($receivedMessage->getConsumerTag(), $receivedMessage);
            };

            $consumerTag = $this->channel->basic_consume(
                $this->queue->getQueueName(),
                $this->getConsumerTag() ?: $this->getQueue()->getConsumerTag(),
                (bool) ($this->getFlags() & InteropAmqpConsumer::FLAG_NOLOCAL),
                (bool) ($this->getFlags() & InteropAmqpConsumer::FLAG_NOACK),
                (bool) ($this->getFlags() & InteropAmqpConsumer::FLAG_EXCLUSIVE),
                (bool) ($this->getFlags() & InteropAmqpConsumer::FLAG_NOWAIT),
                $callback
            );

            $this->consumerTag = $consumerTag ?: $this->getQueue()->getConsumerTag();

            if (empty($this->consumerTag)) {
                throw new Exception('Got empty consumer tag');
            }

            $this->isInit = true;
        }

        if ($message = $this->buffer->pop($this->consumerTag)) {
            return $message;
        }

        try {
            while (true) {
                $start = microtime(true);

                $this->channel->wait(null, false, $timeout / 1000);

                if ($message = $this->buffer->pop($this->consumerTag)) {
                    return $message;
                }

                // is here when consumed message is not for this consumer

                // as timeout is infinite have to continue consumption, but it can overflow message buffer
                if ($timeout <= 0) {
                    continue;
                }

                // compute remaining timeout and continue until time is up
                $stop = microtime(true);
                $timeout -= ($stop - $start) * 1000;

                if ($timeout <= 0) {
                    break;
                }
            }
        } catch (AMQPTimeoutException $e) {
        }
    }
}

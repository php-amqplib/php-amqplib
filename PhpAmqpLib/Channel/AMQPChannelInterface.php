<?php

namespace PhpAmqpLib\Channel;

use PhpAmqpLib\Connection\AMQPConnectionInterface;
use PhpAmqpLib\Exception\AMQPOutOfRangeException;
use PhpAmqpLib\Message\AMQPMessageInterface;

/**
 * AMQPChannelInterface Interface
 */
interface AMQPChannelInterface
{
    /**
     * Get the protocol version
     *
     * @return string
     * @throws AMQPOutOfRangeException
     */
    public static function getProtocolVersion();

    /**
     * Get the ID of the channel
     *
     * @return string
     */
    public function getChannelId();

    /**
     * @param int $max_bytes Max message body size for this channel
     * @return $this
     */
    public function setBodySizeLimit($max_bytes);

    /**
     * Get the connection
     *
     * @return AMQPConnectionInterface
     */
    public function getConnection();

    /**
     * Get the method queue
     *
     * @return array
     */
    public function getMethodQueue();

    /**
     * Indicates whether there are pending methods or not
     *
     * @return bool
     */
    public function hasPendingMethods();

    /**
     * Dispatch a message
     *
     * @param string $method_sig
     * @param string $args
     * @param AMQPMessageInterface|null $amqpMessage
     * @return mixed
     */
    public function dispatch($method_sig, $args, $amqpMessage);
}

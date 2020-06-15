<?php

namespace PhpAmqpLib\Message;

use PhpAmqpLib\Channel\AMQPChannelInterface;

/**
 * AMQPMessageInterface Interface
 */
interface AMQPMessageInterface
{
    /**
     * Acknowledge one or more messages.
     *
     * @param bool $multiple If true, the delivery tag is treated as "up to and including",
     *                       so that multiple messages can be acknowledged with a single method.
     * @since v2.12.0
     * @link https://www.rabbitmq.com/amqp-0-9-1-reference.html#basic.ack
     */
    public function ack($multiple = false);

    /**
     * Reject one or more incoming messages.
     *
     * @param bool $requeue If true, the server will attempt to requeue the message. If requeue is false or the requeue
     *                       attempt fails the messages are discarded or dead-lettered.
     * @param bool $multiple If true, the delivery tag is treated as "up to and including",
     *                       so that multiple messages can be rejected with a single method.
     * @since v2.12.0
     * @link https://www.rabbitmq.com/amqp-0-9-1-reference.html#basic.nack
     */
    public function nack($requeue = false, $multiple = false);

    /**
     * Reject an incoming message.
     *
     * @param bool $requeue If requeue is true, the server will attempt to requeue the message.
     *                      If requeue is false or the requeue attempt fails the messages are discarded or dead-lettered
     * @since v2.12.0
     * @link https://www.rabbitmq.com/amqp-0-9-1-reference.html#basic.reject
     */
    public function reject($requeue = true);

    /**
     * @return AMQPChannelInterface|null
     * @since v2.12.0
     */
    public function getChannel();

    /**
     * Set the channel
     *
     * @param AMQPChannelInterface $channel
     * @return self
     * @since v2.12.0
     */
    public function setChannel($channel);

    /**
     * Set the delivery info
     *
     * @param int $deliveryTag
     * @param bool $redelivered
     * @param string $exchange
     * @param string $routingKey
     * @return self
     * @since v2.12.0
     */
    public function setDeliveryInfo($deliveryTag, $redelivered, $exchange, $routingKey);

    /**
     * Check if a message was redelivered
     *
     * @return bool|null
     * @since v2.12.0
     */
    public function isRedelivered();

    /**
     * Get the exchange of a message
     *
     * @return string|null
     * @since v2.12.0
     */
    public function getExchange();

    /**
     * Get the routing key
     *
     * @return string|null
     * @since v2.12.0
     */
    public function getRoutingKey();

    /**
     * Get the consumer tag
     *
     * @return string|null
     * @since v2.12.0
     */
    public function getConsumerTag();

    /**
     * Set the consumer tag
     *
     * @param string $consumerTag
     * @return self
     * @since v2.12.0
     */
    public function setConsumerTag($consumerTag);

    /**
     * Get the message count
     *
     * @return int|null
     * @since v2.12.0
     */
    public function getMessageCount();

    /**
     * Set the message count
     *
     * @param int $messageCount
     * @return self
     * @since v2.12.0
     */
    public function setMessageCount($messageCount);

    /**
     * Get the payload of a message
     *
     * @return string
     */
    public function getBody();

    /**
     * Sets the message payload
     *
     * @param string $body
     * @return self
     */
    public function setBody($body);

    /**
     * Get the encoding of a message
     *
     * @return string
     */
    public function getContentEncoding();

    /**
     * Get the size of the payload in bytes
     *
     * @return int
     */
    public function getBodySize();

    /**
     * Set the size of the payload
     *
     * @param int $body_size Message body size in byte(s)
     * @return AMQPMessageInterface
     */
    public function setBodySize($body_size);

    /**
     * Indicates whether a payload was truncated or not
     *
     * @return boolean
     */
    public function isTruncated();

    /**
     * Set whether a payload was trunkated or not
     *
     * @param bool $is_truncated
     * @return AMQPMessageInterface
     */
    public function setIsTruncated($is_truncated);

    /**
     * Get the delivery tag
     *
     * @return int
     */
    public function getDeliveryTag();

    /**
     * Set the delivery tag
     *
     * @param int|string $deliveryTag
     * @return self
     * @since v2.12.0
     */
    public function setDeliveryTag($deliveryTag);

    /**
     * Check whether a property exists in the 'properties' dictionary
     * or if present - in the 'delivery_info' dictionary.
     *
     * @param string $name
     * @return bool
     */
    public function has($name);

    /**
     * Look for additional properties in the 'properties' dictionary,
     * and if present - the 'delivery_info' dictionary.
     *
     * @param string $name
     * @return mixed|AMQPChannelInterface
     */
    public function get($name);

    /**
     * Returns the properties content
     *
     * @return array
     */
    public function get_properties();

    /**
     * Sets a property value
     *
     * @param string $name The property name (one of the property definition)
     * @param mixed $value The property value
     */
    public function set($name, $value);

    /**
     * Given the raw bytes containing the property-flags and
     * property-list from a content-frame-header, parse and insert
     * into a dictionary stored in this object as an attribute named
     * 'properties'.
     *
     * @param AMQPChannelInterface $reader
     * NOTE: do not mutate $reader
     * @return self
     */
    public function load_properties($reader);

    /**
     * Serializes the 'properties' attribute (a dictionary) into the
     * raw bytes making up a set of property flags and a property
     * list, suitable for putting into a content frame header.
     *
     * @return string
     */
    public function serialize_properties();
}

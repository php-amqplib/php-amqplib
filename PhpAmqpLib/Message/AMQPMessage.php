<?php

namespace PhpAmqpLib\Message;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPEmptyDeliveryTagException;
use PhpAmqpLib\Wire\AMQPReader;
use PhpAmqpLib\Wire\AMQPWriter;

/**
 * A Message for use with the Channnel.basic_* methods.
 */
class AMQPMessage
{
    const DELIVERY_MODE_NON_PERSISTENT = 1;
    const DELIVERY_MODE_PERSISTENT = 2;

    /**
     * @var string
     * @deprecated Will be removed in version 4.0, use getBody() instead.
     */
    public $body;

    /**
     * @var int
     * @deprecated Will be removed in version 4.0, use getBodySize() instead.
     */
    public $body_size;

    /**
     * @var bool
     * @deprecated Will be removed in version 4.0, use isTruncated() instead.
     */
    public $is_truncated = false;

    /**
     * @var string
     * @deprecated Will be removed in version 4.0, use getContentEncoding() instead.
     */
    public $content_encoding;

    /** @var int */
    private $deliveryTag;

    /** @var string|null */
    private $consumerTag;

    /** @var bool|null */
    private $redelivered;

    /** @var string|null */
    private $exchange;

    /** @var string|null */
    private $routingKey;

    /** @var int|null */
    private $messageCount;

    /** @var AMQPChannel|null */
    private $channel;

    /** @var bool */
    private $responded = false;

    /**
     * @var array
     * @internal
     * @deprecated Will be removed in version 4.0, use one of getters to get delivery info.
     */
    public $delivery_info = array();

    /** @var array Properties content */
    protected $properties = array();

    /** @var null|string Compiled properties */
    protected $serialized_properties;

    /** @var array */
    protected static $propertyDefinitions = array(
        'content_type' => 'shortstr',
        'content_encoding' => 'shortstr',
        'application_headers' => 'table_object',
        'delivery_mode' => 'octet',
        'priority' => 'octet',
        'correlation_id' => 'shortstr',
        'reply_to' => 'shortstr',
        'expiration' => 'shortstr',
        'message_id' => 'shortstr',
        'timestamp' => 'timestamp',
        'type' => 'shortstr',
        'user_id' => 'shortstr',
        'app_id' => 'shortstr',
        'cluster_id' => 'shortstr',
    );

    /**
     * @param string $body
     * @param array $properties
     */
    public function __construct($body = '', $properties = array())
    {
        $this->setBody($body);

        if (!empty($properties) && is_array($properties)) {
            $this->properties = array_intersect_key($properties, self::$propertyDefinitions);
        }
    }

    /**
     * Acknowledge one or more messages.
     *
     * @param bool $multiple If true, the delivery tag is treated as "up to and including",
     *                       so that multiple messages can be acknowledged with a single method.
     * @since 2.12.0
     * @link https://www.rabbitmq.com/amqp-0-9-1-reference.html#basic.ack
     */
    public function ack($multiple = false)
    {
        $this->assertUnacked();
        $this->channel->basic_ack($this->deliveryTag, $multiple);
        $this->onResponse();
    }

    /**
     * Reject one or more incoming messages.
     *
     * @param bool $requeue If true, the server will attempt to requeue the message. If requeue is false or the requeue
     *                       attempt fails the messages are discarded or dead-lettered.
     * @param bool $multiple If true, the delivery tag is treated as "up to and including",
     *                       so that multiple messages can be rejected with a single method.
     * @since 2.12.0
     * @link https://www.rabbitmq.com/amqp-0-9-1-reference.html#basic.nack
     */
    public function nack($requeue = false, $multiple = false)
    {
        $this->assertUnacked();
        $this->channel->basic_nack($this->deliveryTag, $multiple, $requeue);
        $this->onResponse();
    }

    /**
     * Reject an incoming message.
     *
     * @param bool $requeue If requeue is true, the server will attempt to requeue the message.
     *                     If requeue is false or the requeue attempt fails the messages are discarded or dead-lettered.
     * @since 2.12.0
     * @link https://www.rabbitmq.com/amqp-0-9-1-reference.html#basic.reject
     */
    public function reject($requeue = true)
    {
        $this->assertUnacked();
        $this->channel->basic_reject($this->deliveryTag, $requeue);
        $this->onResponse();
    }

    /**
     * @throws \LogicException When response to broker was already sent.
     */
    protected function assertUnacked()
    {
        if (!$this->channel || $this->responded) {
            throw new \LogicException('Message is not published or response was already sent');
        }
    }

    protected function onResponse()
    {
        $this->responded = true;
    }

    /**
     * @return AMQPChannel|null
     * @since 2.12.0
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param AMQPChannel $channel
     * @return $this
     * @throws \RuntimeException
     * @since 2.12.0
     */
    public function setChannel($channel)
    {
        if ($this->channel) {
            throw new \RuntimeException('A message is already assigned to channel');
        }
        $this->channel = $channel;
        $this->delivery_info['channel'] = $channel;

        return $this;
    }

    /**
     * @param int $deliveryTag
     * @param bool $redelivered
     * @param string $exchange
     * @param string $routingKey
     * @return $this
     * @since 2.12.0
     */
    public function setDeliveryInfo($deliveryTag, $redelivered, $exchange, $routingKey)
    {
        $this->deliveryTag = $this->delivery_info['delivery_tag'] = $deliveryTag;
        $this->redelivered = $this->delivery_info['redelivered'] = $redelivered;
        $this->exchange = $this->delivery_info['exchange'] = $exchange;
        $this->routingKey = $this->delivery_info['routing_key'] = $routingKey;

        return $this;
    }

    /**
     * @return bool|null
     * @since 2.12.0
     */
    public function isRedelivered()
    {
        return $this->redelivered;
    }

    /**
     * @return string|null
     * @since 2.12.0
     */
    public function getExchange()
    {
        return $this->exchange;
    }

    /**
     * @return string|null
     * @since 2.12.0
     */
    public function getRoutingKey()
    {
        return $this->routingKey;
    }

    /**
     * @return string|null
     * @since 2.12.0
     */
    public function getConsumerTag()
    {
        return $this->consumerTag;
    }

    /**
     * @param string $consumerTag
     * @return $this
     * @since 2.12.0
     */
    public function setConsumerTag($consumerTag)
    {
        $this->consumerTag = $consumerTag;
        $this->delivery_info['consumer_tag'] = $consumerTag;

        return $this;
    }

    /**
     * @return int|null
     * @since 2.12.0
     */
    public function getMessageCount()
    {
        return $this->messageCount;
    }

    /**
     * @param int $messageCount
     * @return $this
     * @since 2.12.0
     */
    public function setMessageCount($messageCount)
    {
        $this->messageCount = (int)$messageCount;
        $this->delivery_info['message_count'] = $this->messageCount;

        return $this;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Sets the message payload
     *
     * @param string $body
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @return string
     */
    public function getContentEncoding()
    {
        return $this->content_encoding;
    }

    /**
     * @return int
     */
    public function getBodySize()
    {
        return $this->body_size;
    }

    /**
     * @param int $body_size Message body size in byte(s)
     * @return AMQPMessage
     */
    public function setBodySize($body_size)
    {
        $this->body_size = (int)$body_size;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isTruncated()
    {
        return $this->is_truncated;
    }

    /**
     * @param bool $is_truncated
     * @return AMQPMessage
     */
    public function setIsTruncated($is_truncated)
    {
        $this->is_truncated = (bool)$is_truncated;

        return $this;
    }

    /**
     * @param int|string $deliveryTag
     * @return $this
     * @since 2.12.0
     */
    public function setDeliveryTag($deliveryTag)
    {
        if (!empty($this->deliveryTag)) {
            throw new \LogicException('Delivery tag cannot be changed');
        }
        $this->deliveryTag = $deliveryTag;
        $this->delivery_info['delivery_tag'] = $deliveryTag;

        return $this;
    }

    /**
     * @return int
     *
     * @throws AMQPEmptyDeliveryTagException
     */
    public function getDeliveryTag()
    {
        if (empty($this->deliveryTag)) {
            throw new AMQPEmptyDeliveryTagException('This message was not delivered yet');
        }

        return $this->deliveryTag;
    }

    /**
     * Check whether a property exists in the 'properties' dictionary
     * or if present - in the 'delivery_info' dictionary.
     *
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        return isset($this->properties[$name]) || isset($this->delivery_info[$name]);
    }

    /**
     * Look for additional properties in the 'properties' dictionary,
     * and if present - the 'delivery_info' dictionary.
     *
     * @param string $name
     * @return mixed|AMQPChannel
     * @throws \OutOfBoundsException
     */
    public function get($name)
    {
        if (isset($this->properties[$name])) {
            return $this->properties[$name];
        }

        if (isset($this->delivery_info[$name])) {
            return $this->delivery_info[$name];
        }

        throw new \OutOfBoundsException(sprintf(
            'No "%s" property',
            $name
        ));
    }

    /**
     * Returns the properties content
     *
     * @return array
     */
    public function get_properties()
    {
        return $this->properties;
    }

    /**
     * Sets a property value
     *
     * @param string $name The property name (one of the property definition)
     * @param mixed $value The property value
     * @throws \OutOfBoundsException
     */
    public function set($name, $value)
    {
        if (!array_key_exists($name, self::$propertyDefinitions)) {
            throw new \OutOfBoundsException(sprintf(
                'No "%s" property',
                $name
            ));
        }

        if (isset($this->properties[$name]) && $this->properties[$name] === $value) {
            // same value, nothing to do
            return;
        }

        $this->properties[$name] = $value;
        $this->serialized_properties = null;
    }

    /**
     * Given the raw bytes containing the property-flags and
     * property-list from a content-frame-header, parse and insert
     * into a dictionary stored in this object as an attribute named
     * 'properties'.
     *
     * @param AMQPReader $reader
     * NOTE: do not mutate $reader
     * @return $this
     */
    public function load_properties(AMQPReader $reader)
    {
        // Read 16-bit shorts until we get one with a low bit set to zero
        $flags = array();

        while (true) {
            $flag_bits = $reader->read_short();
            $flags[] = $flag_bits;

            if (($flag_bits & 1) === 0) {
                break;
            }
        }

        $shift = 0;
        $data = array();

        foreach (self::$propertyDefinitions as $key => $proptype) {
            if ($shift === 0) {
                if (!$flags) {
                    break;
                }
                $flag_bits = array_shift($flags);
                $shift = 15;
            }

            if ($flag_bits & (1 << $shift)) {
                $data[$key] = $reader->{'read_' . $proptype}();
            }

            $shift -= 1;
        }

        $this->properties = $data;

        return $this;
    }


    /**
     * Serializes the 'properties' attribute (a dictionary) into the
     * raw bytes making up a set of property flags and a property
     * list, suitable for putting into a content frame header.
     *
     * @return string
     * @todo Inject the AMQPWriter to make the method easier to test
     */
    public function serialize_properties()
    {
        if (!empty($this->serialized_properties)) {
            return $this->serialized_properties;
        }

        $shift = 15;
        $flag_bits = 0;
        $flags = array();
        $raw_bytes = new AMQPWriter();

        foreach (self::$propertyDefinitions as $key => $prototype) {
            $val = isset($this->properties[$key]) ? $this->properties[$key] : null;

            // Very important: PHP type eval is weak, use the === to test the
            // value content. Zero or false value should not be removed
            if ($val === null) {
                $shift -= 1;
                continue;
            }

            if ($shift === 0) {
                $flags[] = $flag_bits;
                $flag_bits = 0;
                $shift = 15;
            }

            $flag_bits |= (1 << $shift);
            if ($prototype !== 'bit') {
                $raw_bytes->{'write_' . $prototype}($val);
            }

            $shift -= 1;
        }

        $flags[] = $flag_bits;
        $result = new AMQPWriter();
        foreach ($flags as $flag_bits) {
            $result->write_short($flag_bits);
        }

        $result->write($raw_bytes->getvalue());

        $this->serialized_properties = $result->getvalue();

        return $this->serialized_properties;
    }
}

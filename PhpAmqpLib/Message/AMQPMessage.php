<?php
namespace PhpAmqpLib\Message;

use PhpAmqpLib\Wire\GenericContent;

/**
 * A Message for use with the Channnel.basic_* methods.
 */
class AMQPMessage extends GenericContent
{
    const DELIVERY_MODE_NON_PERSISTENT = 1;
    const DELIVERY_MODE_PERSISTENT = 2;

    /** @var string */
    public $body;
    public $body_size;
    public $is_truncated = false;

    /** @var string */
    public $content_encoding;

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
     * @param null $properties
     */
    public function __construct($body = '', $properties = null)
    {
        $this->setBody($body);
        parent::__construct($properties, static::$propertyDefinitions);
    }

    /**
     * Sets the message payload
     *
     * @param mixed $body
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;
    }
}

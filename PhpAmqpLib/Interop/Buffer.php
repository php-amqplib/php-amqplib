<?php

namespace PhpAmqpLib\Interop;

use Interop\Amqp\AmqpMessage;

class Buffer
{
    /**
     * @var array ['aTag' => [AmqpMessage, AmqpMessage ...]]
     */
    private $messages;

    public function __construct()
    {
        $this->messages = [];
    }

    /**
     * @param string      $consumerTag
     * @param AmqpMessage $message
     */
    public function push($consumerTag, AmqpMessage $message)
    {
        if (false == array_key_exists($consumerTag, $this->messages)) {
            $this->messages[$consumerTag] = [];
        }

        $this->messages[$consumerTag][] = $message;
    }

    /**
     * @param string $consumerTag
     *
     * @return AmqpMessage|null
     */
    public function pop($consumerTag)
    {
        if (false == empty($this->messages[$consumerTag])) {
            return array_shift($this->messages[$consumerTag]);
        }
    }
}

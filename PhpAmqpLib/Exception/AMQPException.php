<?php

namespace PhpAmqpLib\Exception;

/**
 * @deprecated use AMQPProtocolException instead
 */
class AMQPException extends \Exception
{
    /** @var int */
    public $amqp_reply_code;

    /** @var string */
    public $amqp_reply_text;

    /** @var int[] */
    public $amqp_method_sig;

    /** @var array */
    public $args;

    /**
     * @param int $reply_code
     * @param string $reply_text
     * @param int[] $method_sig
     */
    public function __construct($reply_code, $reply_text, $method_sig)
    {
        parent::__construct($reply_text, $reply_code);

        $this->amqp_reply_code = $reply_code; // redundant, but kept for BC
        $this->amqp_reply_text = $reply_text; // redundant, but kept for BC
        $this->amqp_method_sig = $method_sig;

        $this->args = array($reply_code, $reply_text, $method_sig, '');
    }
}

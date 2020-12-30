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
     * @param int $replyCode
     * @param string $replyText
     * @param int[] $methodSig
     */
    public function __construct($replyCode, $replyText, $methodSig)
    {
        parent::__construct($replyText, $replyCode);

        $this->amqp_reply_code = $replyCode; // redundant, but kept for BC
        $this->amqp_reply_text = $replyText; // redundant, but kept for BC
        $this->amqp_method_sig = $methodSig;

        $this->args = array($replyCode, $replyText, $methodSig, '');
    }
}

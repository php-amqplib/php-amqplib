<?php

namespace PhpAmqpLib\Exception;

//TODO refactor usage of static methods
use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Helper\MiscHelper;

class AMQPProtocolException extends \Exception implements AMQPExceptionInterface
{
    public function __construct($reply_code, $reply_text, $method_sig)
    {
        parent::__construct($reply_text,$reply_code);

        $this->amqp_reply_code = $reply_code; // redundant, but kept for BC
        $this->amqp_reply_text = $reply_text; // redundant, but kept for BC
        $this->amqp_method_sig = $method_sig;

        $ms = MiscHelper::methodSig($method_sig);

        $mn = isset(AbstractChannel::$GLOBAL_METHOD_NAMES[$ms])
                ? AbstractChannel::$GLOBAL_METHOD_NAMES[$ms]
                : $mn = "";

        $this->args = array(
            $reply_code,
            $reply_text,
            $method_sig,
            $mn
        );
    }
}

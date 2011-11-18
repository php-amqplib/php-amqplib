<?php

namespace PhpAmqpLib\Exception;

use PhpAmqpLib\Exception\AMQPException;

class AMQPConnectionException extends AMQPException
{
    public function __construct($reply_code, $reply_text, $method_sig)
    {
        parent::__construct($reply_code, $reply_text, $method_sig);
    }
}
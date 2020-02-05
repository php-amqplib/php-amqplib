<?php

namespace PhpAmqpLib\Exception;

class AMQPConnectionBlockedException extends AMQPRuntimeException
{
    public function __construct($message = '', $code = 0, $previous = null)
    {
        if (empty($message)) {
            $message = 'Connection is blocked due to low resources';
        }
        parent::__construct($message, $code, $previous);
    }
}

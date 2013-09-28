<?php

namespace PhpAmqpLib\Exception;

class AMQPBasicCancelException extends \Exception implements AMQPExceptionInterface
{
    public $consumerTag;

    public function __construct($consumerTag)
    {
        $this->consumerTag = $consumerTag;
    }
}
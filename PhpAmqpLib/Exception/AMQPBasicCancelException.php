<?php
namespace PhpAmqpLib\Exception;

class AMQPBasicCancelException extends \Exception implements AMQPExceptionInterface
{
    /**
     * @var string
     * @internal Use getter getConsumerTag()
     */
    public $consumerTag;

    /**
     * @param string $consumerTag
     */
    public function __construct($consumerTag)
    {
        parent::__construct('Channel was canceled');
        $this->consumerTag = $consumerTag;
    }

    /**
     * @return string
     */
    public function getConsumerTag()
    {
        return $this->consumerTag;
    }
}

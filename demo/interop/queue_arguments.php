<?php

use Interop\Amqp\AmqpQueue;
use PhpAmqpLib\Interop\AmqpConnectionFactory;

include(__DIR__ . '/config.php');

$exchangeName = 'router';
$queueName = 'haqueue';
$specificQueueName = 'specific-haqueue';

$context = (new AmqpConnectionFactory(AMQP_DSN))->createContext();

$queue = $context->createQueue($queueName);
$queue->addFlag(AmqpQueue::FLAG_DURABLE);
$queue->setArguments(array(
    "x-dead-letter-exchange" => "t_test1",
    "x-message-ttl" => 15000,
    "x-expires" => 16000
));

$context->close();
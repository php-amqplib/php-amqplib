<?php

use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\Impl\AmqpBind;
use PhpAmqpLib\Interop\AmqpConnectionFactory;

include(__DIR__ . '/config.php');

$exchangeName = 'router';
$queueName = 'msgs';

$context = (new AmqpConnectionFactory(AMQP_DSN))->createContext();

$queue = $context->createQueue($queueName);
$queue->addFlag(AmqpQueue::FLAG_DURABLE);
$context->declareQueue($queue);

$topic = $context->createTopic($exchangeName);
$topic->setType(AmqpTopic::TYPE_DIRECT);
$topic->addFlag(AmqpTopic::FLAG_DURABLE);
$context->declareTopic($topic);

$context->bind(new AmqpBind($topic, $queue));

$message = $context->createMessage(implode(' ', array_slice($argv, 1)));
$message->setContentType('text/plain');
$message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);

$context->createProducer()->send($topic, $message);

$context->close();
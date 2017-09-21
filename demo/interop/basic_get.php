<?php

use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use PhpAmqpLib\Interop\AmqpConnectionFactory;

include(__DIR__ . '/config.php');

$exchangeName = 'basic_get_test';
$queueName = 'basic_get_queue';

$context = (new AmqpConnectionFactory(AMQP_DSN))->createContext();

$queue = $context->createQueue($queueName);
$queue->addFlag(AmqpQueue::FLAG_DURABLE);
$context->declareQueue($queue);

$topic = $context->createTopic($exchangeName);
$topic->setType(AmqpTopic::TYPE_DIRECT);
$topic->addFlag(AmqpTopic::FLAG_DURABLE);
$context->declareTopic($topic);

$context->bind(new AmqpBind($topic, $queue));

$toSend = $context->createMessage('test message');
$toSend->setContentType('text/plain');
$toSend->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);

$context->createProducer()->send($topic, $toSend);

$consumer = $context->createConsumer($queue);
$message = $consumer->receiveNoWait();

$consumer->acknowledge($message);

var_dump($message->getBody());

$context->close();
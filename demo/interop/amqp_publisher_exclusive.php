<?php

use Interop\Amqp\AmqpTopic;
use PhpAmqpLib\Interop\AmqpConnectionFactory;

include(__DIR__ . '/config.php');

$exchangeName = 'fanout_exclusive_example_exchange';

$context = (new AmqpConnectionFactory(AMQP_DSN))->createContext();

$topic = $context->createTopic($exchangeName);
$topic->setType(AmqpTopic::TYPE_FANOUT);
$topic->addFlag(AmqpTopic::FLAG_AUTODELETE);
$context->declareTopic($topic);

$message = $context->createMessage(implode(' ', array_slice($argv, 1)));
$message->setContentType('text/plain');

$context->createProducer()->send($topic, $message);

$context->close();
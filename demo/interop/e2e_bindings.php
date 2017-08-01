<?php

use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use PhpAmqpLib\Interop\AmqpConnectionFactory;

include(__DIR__ . '/config.php');

$sourceName = 'my_source_exchange';
$destinationName = 'my_dest_exchange';

$context = (new AmqpConnectionFactory(AMQP_DSN))->createContext();

$source = $context->createTopic($exchangeName);
$source->setType(AmqpTopic::TYPE_TOPIC);
$source->addFlag(AmqpTopic::FLAG_DURABLE);
$context->declareTopic($source);

$destination = $context->createTopic($exchangeName);
$destination->setType(AmqpTopic::TYPE_DIRECT);
$destination->addFlag(AmqpTopic::FLAG_DURABLE);
$context->declareTopic($destination);

$context->bind(new AmqpBind($source, $destination));

$context->unbind(new AmqpBind($source, $destination));

$context->close();
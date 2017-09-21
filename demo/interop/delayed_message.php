<?php

use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use PhpAmqpLib\Interop\AmqpConnectionFactory;

include(__DIR__ . '/config.php');

$exchangeName = 'delayed_exchange';
$queueName = 'delayed_queue';

$context = (new AmqpConnectionFactory(AMQP_DSN))->createContext();

$queue = $context->createQueue($queueName);
$queue->setArgument('x-dead-letter-exchange', 'delayed');
$context->declareQueue($queue);

$topic = $context->createTopic($exchangeName);
$topic->setType('x-delayed-message');
$topic->addFlag(AmqpTopic::FLAG_DURABLE);
$topic->setArgument('x-delayed-type', AmqpTopic::TYPE_FANOUT);
$context->declareTopic($topic);

$context->bind(new AmqpBind($topic, $queue));

$message = $context->createMessage('hello', ['x-delay' => 7000]);
$message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);
$context->createProducer()->send($topic, $message);

$consumer = $context->createConsumer($queue);
$consumer->addFlag(AmqpConsumer::FLAG_NOACK);
$consumer->setConsumerTag($consumerTag);

while (true) {
    if (false == $message = $consumer->receive(1000)) {
        continue;
    }

    $properties = $message->getProperties();
    var_dump($properties['x-delay']);

    $consumer->reject($message);
}

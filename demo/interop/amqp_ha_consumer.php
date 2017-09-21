<?php

use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use PhpAmqpLib\Interop\AmqpConnectionFactory;

include(__DIR__ . '/config.php');

$exchangeName = 'router';
$queueName = 'haqueue';
$specificQueueName = 'specific-haqueue';
$consumerTag = 'consumer';

$context = (new AmqpConnectionFactory(AMQP_DSN))->createContext();

$queue = $context->createQueue($queueName);
$queue->setArguments(array('x-ha-policy' => 'all'));
$context->declareQueue($queue);

$specificQueue = $context->createQueue($specificQueueName);
$specificQueue->setArguments(array(
    'x-ha-policy' => 'nodes',
    'x-ha-policy-params' => array(
        'rabbit@' . HOST,
        'hare@' . HOST,
    ),
));
$context->declareQueue($specificQueue);

$topic = $context->createTopic($exchangeName);
$topic->setType(AmqpTopic::TYPE_DIRECT);
$topic->addFlag(AmqpTopic::FLAG_DURABLE);
$context->declareTopic($topic);

$context->bind(new AmqpBind($topic, $queue));
$context->bind(new AmqpBind($topic, $specificQueue));

$consumer = $context->createConsumer($queue);
$consumer->setConsumerTag($consumerTag);

while (true) {
    if (false == $message = $consumer->receive(1000)) {
        continue;
    }

    echo "\n--------\n";
    echo $message->getBody();
    echo "\n--------\n";

    $consumer->acknowledge($message);

    // Send a message with the string "quit" to cancel the consumer.
    if ($message->getBody() === 'quit') {
        break;
    }
}
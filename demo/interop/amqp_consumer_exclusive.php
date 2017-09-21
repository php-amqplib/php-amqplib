<?php

// Only one consumer per queue is allowed.
// Set $queue name to test exclusiveness

use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use PhpAmqpLib\Interop\AmqpConnectionFactory;

include(__DIR__ . '/config.php');

$exchangeName = 'fanout_exclusive_example_exchange';
$consumerTag = 'consumer' . getmypid();

$context = (new AmqpConnectionFactory(AMQP_DSN))->createContext();

$queue = $context->createTemporaryQueue();
$queue->addFlag(AmqpQueue::FLAG_DURABLE);
$queue->addFlag(AmqpQueue::FLAG_EXCLUSIVE);
$context->declareQueue($queue);

$topic = $context->createTopic($exchangeName);
$topic->setType(AmqpTopic::TYPE_FANOUT);
$topic->addFlag(AmqpTopic::FLAG_DURABLE);
$context->declareTopic($topic);

$context->bind(new AmqpBind($topic, $queue));

$consumer = $context->createConsumer($queue);
$consumer->addFlag(AmqpConsumer::FLAG_EXCLUSIVE);
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

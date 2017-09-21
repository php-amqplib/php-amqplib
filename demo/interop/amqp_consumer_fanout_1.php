<?php

// Run multiple instances of amqp_consumer_fanout_1.php and
// amqp_consumer_fanout_2.php to test

include(__DIR__ . '/config.php');

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use PhpAmqpLib\Interop\AmqpConnectionFactory;

$exchangeName = 'fanout_example_exchange';
$queueName = 'fanout_group_1';
$consumerTag = 'consumer' . getmypid();

$context = (new AmqpConnectionFactory(AMQP_DSN))->createContext();

$queue = $context->createQueue($queueName);
$queue->addFlag(AmqpQueue::FLAG_AUTODELETE);
$context->declareQueue($queue);

$topic = $context->createTopic($exchangeName);
$topic->setType(AmqpTopic::TYPE_FANOUT);
$topic->addFlag(AmqpTopic::FLAG_AUTODELETE);
$context->declareTopic($topic);

$context->bind(new AmqpBind($topic, $queue));

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

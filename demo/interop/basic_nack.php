<?php
/**
 * - Start this consumer in one window by calling: php demo/basic_nack.php
 * - Then on a separate window publish a message like this: php demo/amqp_publisher.php good
 *   that message should be "ack'ed"
 * - Then publish a message like this: php demo/amqp_publisher.php bad
 *   that message should be "nack'ed"
 */

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use PhpAmqpLib\Interop\AmqpConnectionFactory;

include(__DIR__ . '/config.php');

$exchangeName = 'router';
$queueName = 'msgs';
$consumerTag = 'consumer';

$context = (new AmqpConnectionFactory(AMQP_DSN))->createContext();

$queue = $context->createQueue($queueName);
$queue->addFlag(AmqpQueue::FLAG_DURABLE);
$context->declareQueue($queue);

$topic = $context->createTopic($exchangeName);
$topic->setType(AmqpTopic::TYPE_DIRECT);
$topic->addFlag(AmqpTopic::FLAG_DURABLE);
$context->declareTopic($topic);

$context->bind(new AmqpBind($topic, $queue));

$consumer = $context->createConsumer($queue);
$consumer->setConsumerTag($consumerTag);

while (true) {
    if (false == $message = $consumer->receive(1000)) {
        continue;
    }

    if ($message->getBody() == 'good') {
        $consumer->acknowledge($message);
    } else {
        $consumer->reject($message);
    }

    // Send a message with the string "quit" to cancel the consumer.
    if ($message->getBody() === 'quit') {
        $context->close();
        break;
    }
}

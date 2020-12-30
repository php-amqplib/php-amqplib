<?php
/**
 * - Start this consumer in one window by calling: php demo/basic_nack.php
 * - Then on a separate window publish a message like this: php demo/amqp_publisher.php good
 *   that message should be "ack'ed"
 * - Then publish a message like this: php demo/amqp_publisher.php bad
 *   that message should be "nack'ed"
 */
include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

$exchange = 'router';
$queue = 'msgs';
$consumerTag = 'consumer';

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$channel->queueDeclare($queue, false, true, false, false);
$channel->exchangeDeclare($exchange, AMQPExchangeType::DIRECT, false, true, false);
$channel->queueBind($queue, $exchange);

/**
 * @param \PhpAmqpLib\Message\AMQPMessage $message
 */
function process_message($message)
{
    if ($message->body == 'good') {
        $message->ack();
    } else {
        $message->nack();
    }

    // Send a message with the string "quit" to cancel the consumer.
    if ($message->body === 'quit') {
        $message->getChannel()->basicCancel($message->getConsumerTag());
    }
}

$channel->basicConsume($queue, $consumerTag, false, false, false, false, 'process_message');

/**
 * @param \PhpAmqpLib\Channel\AMQPChannel $channel
 * @param \PhpAmqpLib\Connection\AbstractConnection $connection
 */
function shutdown($channel, $connection)
{
    $channel->close();
    $connection->close();
}

register_shutdown_function('shutdown', $channel, $connection);

// Loop as long as the channel has callbacks registered
while ($channel->isConsuming()) {
    $channel->wait();
}

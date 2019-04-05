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

$channel->queue_declare($queue, false, true, false, false);
$channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
$channel->queue_bind($queue, $exchange);

/**
 * @param \PhpAmqpLib\Message\AMQPMessage $message
 */
function process_message($message)
{
    if ($message->body == 'good') {
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    } else {
        $message->delivery_info['channel']->basic_nack($message->delivery_info['delivery_tag']);
    }

    // Send a message with the string "quit" to cancel the consumer.
    if ($message->body === 'quit') {
        $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
    }
}

$channel->basic_consume($queue, $consumerTag, false, false, false, false, 'process_message');

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
while ($channel->is_consuming()) {
    $channel->wait();
}

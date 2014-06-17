<?php
/**
 * - Start this consumer in one window by calling: php demo/basic_nack.php
 * - Then on a separate window publish a message like this: php demo/amqp_publisher.php good
 *   that message should be "ack'ed"
 * - Then publish a message like this: php demo/amqp_publisher.php bad
 *   that message should be "nack'ed"
 */


include(__DIR__ . '/config.php');
use PhpAmqpLib\Connection\AMQPConnection;

$exchange = 'router';
$queue = 'msgs';
$consumer_tag = 'consumer';

$conn = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
$ch = $conn->channel();

$ch->queue_declare($queue, false, true, false, false);
$ch->exchange_declare($exchange, 'direct', false, true, false);
$ch->queue_bind($queue, $exchange);

/**
 * @param \PhpAmqpLib\Message\AMQPMessage $msg
 */
function process_message($msg)
{
    if ($msg->body == 'good') {
        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    } else {
        $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag']);
    }

    // Send a message with the string "quit" to cancel the consumer.
    if ($msg->body === 'quit') {
        $msg->delivery_info['channel']->basic_cancel($msg->delivery_info['consumer_tag']);
    }
}

$ch->basic_consume($queue, $consumer_tag, false, false, false, false, 'process_message');

/**
 * @param \PhpAmqpLib\Channel\AMQPChannel $ch
 * @param \PhpAmqpLib\Connection\AbstractConnection $conn
 */
function shutdown($ch, $conn)
{
    $ch->close();
    $conn->close();
}

register_shutdown_function('shutdown', $ch, $conn);

// Loop as long as the channel has callbacks registered
while (count($ch->callbacks)) {
    $ch->wait();
}

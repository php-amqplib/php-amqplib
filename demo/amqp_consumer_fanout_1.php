<?php

// Run multiple instances of amqp_consumer_fanout_1.php and
// amqp_consumer_fanout_2.php to test

include(__DIR__ . '/config.php');
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

$exchange = 'fanout_example_exchange';
$queue = 'fanout_group_1';
$consumerTag = 'consumer' . getmypid();

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

/*
    name: $queue    // should be unique in fanout exchange.
    passive: false  // don't check if a queue with the same name exists
    durable: false // the queue will not survive server restarts
    exclusive: false // the queue might be accessed by other channels
    auto_delete: true //the queue will be deleted once the channel is closed.
*/
$channel->queue_declare($queue, false, false, false, true);

/*
    name: $exchange
    type: direct
    passive: false // don't check if a exchange with the same name exists
    durable: false // the exchange will not survive server restarts
    auto_delete: true //the exchange will be deleted once the channel is closed.
*/

$channel->exchange_declare($exchange, AMQPExchangeType::FANOUT, false, false, true);

$channel->queue_bind($queue, $exchange);

/**
 * @param \PhpAmqpLib\Message\AMQPMessage $message
 */
function process_message($message)
{
    echo "\n--------\n";
    echo $message->body;
    echo "\n--------\n";

    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);

    // Send a message with the string "quit" to cancel the consumer.
    if ($message->body === 'quit') {
        $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
    }
}

/*
    queue: Queue from where to get the messages
    consumer_tag: Consumer identifier
    no_local: Don't receive messages published by this consumer.
    no_ack: If set to true, automatic acknowledgement mode will be used by this consumer. See https://www.rabbitmq.com/confirms.html for details.
    exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
    nowait: don't wait for a server response. In case of error the server will raise a channel
            exception
    callback: A PHP Callback
*/

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

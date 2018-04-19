<?php

include(__DIR__ . '/config.php');
use PhpAmqpLib\Connection\AMQPStreamConnection;

const WAIT_BEFORE_RECONNECT_uS = 1000000;

function cleanup_connection($connection) {
    // Connection might already be closed.
    // Ignoring exceptions.
    try {
        if($connection !== null) {
            $connection->close();
        }
    } catch (\ErrorException $e) {
    }
}

$connection = null;

while(true){
    try {
        $connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
        register_shutdown_function('shutdown', $connection);
        // Your application code goes here.
        do_something_with_connection($connection);
    } catch(AMQPIOException $e) {
        echo "AMQP IO exception " . PHP_EOL;
        cleanup_connection($connection);
        usleep(WAIT_BEFORE_RECONNECT_uS);
    } catch(\RuntimeException $e) {
        echo "Runtime exception " . PHP_EOL;
        cleanup_connection($connection);
        usleep(WAIT_BEFORE_RECONNECT_uS);
    } catch(\ErrorException $e) {
        echo "Error exception " . PHP_EOL;
        cleanup_connection($connection);
        usleep(WAIT_BEFORE_RECONNECT_uS);
    }
}

function do_something_with_connection($connection) {
    $queue = 'receive';
    $consumerTag = 'consumer';
    $channel = $connection->channel();
    $channel->queue_declare($queue, false, true, false, false);
    $channel->basic_consume($queue, $consumerTag, false, false, false, false, 'process_message');
    while (count($channel->callbacks)) {
        $channel->wait();
    }
}


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

/**
 * @param \PhpAmqpLib\Connection\AbstractConnection $connection
 */
function shutdown($connection)
{
    $connection->close();
}



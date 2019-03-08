<?php

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;

const WAIT_BEFORE_RECONNECT_uS = 1000000;

// Assume we have a cluster of nodes on ports 5672, 5673 and 5674.
// This should be possible to start on localhost using RABBITMQ_NODE_PORT
const PORT1 = 5672;
const PORT2 = 5673;
const PORT3 = 5674;

/*
    To handle arbitrary node restart you can use a combination of connection
    recovery and mulltiple hosts connection.
*/

function connect() {
    // If you want a better load-balancing, you cann reshuffle the list.
    return AMQPStreamConnection::create_connection([
        ['host' => HOST, 'port' => PORT1, 'user' => USER, 'password' => PASS, 'vhost' => VHOST],
        ['host' => HOST, 'port' => PORT2, 'user' => USER, 'password' => PASS, 'vhost' => VHOST],
        ['host' => HOST, 'port' => PORT3, 'user' => USER, 'password' => PASS, 'vhost' => VHOST]
    ],
    [
        'insist' => false,
        'login_method' => 'AMQPLAIN',
        'login_response' => null,
        'locale' => 'en_US',
        'connection_timeout' => 3.0,
        'read_write_timeout' => 3.0,
        'context' => null,
        'keepalive' => false,
        'heartbeat' => 0
    ]);
}

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
        $connection = connect();
        register_shutdown_function('shutdown', $connection);
        // Your application code goes here.
        do_something_with_connection($connection);
    } catch(AMQPRuntimeException $e) {
        echo $e->getMessage() . PHP_EOL;
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



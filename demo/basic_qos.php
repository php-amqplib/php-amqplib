<?php

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);

$channel = $connection->channel();

$channel->queueDeclare('qos_queue', false, true, false, false);

$channel->basicQos(null, 10000, null);

function process_message($message)
{
    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
}

$channel->basicConsume('qos_queue', '', false, false, false, false, 'process_message');

while ($channel->isConsuming()) {
    // After 10 seconds there will be a timeout exception.
    $channel->wait(null, false, 10);
}

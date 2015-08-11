<?php

include(__DIR__ . '/config.php');
use PhpAmqpLib\Connection\AMQPConnection;

$conn = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);

$ch = $conn->channel();

$ch->queue_declare('qos_queue', false, true, false, false);

$ch->basic_qos(null, 10000, null);

function process_message($msg) {
    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
}

$ch->basic_consume('qos_queue', '', false, false, false, false, 'process_message');

while (count($ch->callbacks)) {
    // After 10 seconds there will be a timeout exception.
    $ch->wait(null, false, 10);
}
<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Helper\Protocol\Wait091;

include(__DIR__ . '/config.php');

$queue = 'msgs';
$consumerTag = 'consumer';

/*
 * Watch the debug output opening the connection. php-amqplib will send a capabilities table to the server
 * indicating that it's able to receive and process basic.cancel frames by setting the field
 * 'consumer_cancel_notify' to true.
 */
$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();
$channel->queue_declare($queue);

$waitHelper = new Wait091();

$channel->basic_consume($queue, $consumerTag);
$channel->queue_delete($queue);
/*
 * if the server is capable of sending basic.cancel messages, too, this call will end in an AMQPBasicCancelException.
 */
$channel->wait(array($waitHelper->get_wait('basic.cancel')));

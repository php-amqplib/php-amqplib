<?php

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Helper\Protocol\Wait091;

include(__DIR__ . '/config.php');

$queue = 'msgs';
$consumer_tag = 'consumer';

/*
 * Watch the debug output opening the connection. php-amqplib will send a capabilities table to the server
 * indicating that it's able to receive and process basic.cancel frames by setting the field
 * 'consumer_cancel_notify' to true.
 */
$conn = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
$ch = $conn->channel();
$ch->queue_declare($queue);

$waitHelper = new Wait091();

$ch->basic_consume($queue, $consumer_tag);
$ch->queue_delete($queue);
/*
 * if the server is capable of sending basic.cancel messages, too, this call will end in an AMQPBasicCancelException.
 */
$ch->wait(array($waitHelper->get_wait('basic.cancel')));

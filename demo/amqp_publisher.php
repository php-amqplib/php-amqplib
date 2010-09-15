#!/usr/bin/php
<?php
/**
 * Sends a message to a queue
 *
 * @author Sean Murphy<sean@iamseanmurphy.com>
 */
 
require_once('../amqp.inc');

$HOST = 'localhost';
$PORT = 5672;
$USER = 'guest';
$PASS = 'guest';
$VHOST = '/';
$EXCHANGE = 'router';
$QUEUE = 'msgs';

$conn = new AMQPConnection($HOST, $PORT, $USER, $PASS);
$ch = $conn->channel();
$ch->access_request($VHOST, false, false, true, true);

$msg_body = implode(' ', array_slice($argv, 1));
$msg = new AMQPMessage($msg_body, array('content_type' => 'text/plain'));
$ch->basic_publish($msg, $EXCHANGE);

$ch->close();
$conn->close();
?>

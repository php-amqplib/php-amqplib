<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

include(__DIR__ . '/config.php');

$conn = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$ch = $conn->channel();

/**
 * Declares exchange
 *
 * @param string $exchange
 * @param string $type
 * @param bool $passive
 * @param bool $durable
 * @param bool $auto_delete
 * @param bool $internal
 * @param bool $nowait
 * @return mixed|null
 */
$ch->exchange_declare('delayed_exchange', 'x-delayed-message', false, true, false, false, false, new AMQPTable(array(
   "x-delayed-type" => "fanout"
)));

/**
 * Declares queue, creates if needed
 *
 * @param string $queue
 * @param bool $passive
 * @param bool $durable
 * @param bool $exclusive
 * @param bool $auto_delete
 * @param bool $nowait
 * @param null $arguments
 * @param null $ticket
 * @return mixed|null
 */
$ch->queue_declare('delayed_queue', false, false, false, false, false, new AMQPTable(array(
   "x-dead-letter-exchange" => "delayed"
)));

$ch->queue_bind('delayed_queue', 'delayed_exchange');

$hdrs = new AMQPTable(array("x-delay" => 7000));
$msg = new AMQPMessage('hello', array('delivery_mode' => 2));
$msg->set('application_headers', $hdrs);
$ch->basic_publish($msg, 'delayed_exchange');

function process_message($msg) {
    $hdrs = $msg->get('application_headers');
    $arr = $hdrs->getNativeData();
    var_dump($arr['x-delay']);
    $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag']);
}

/*
    queue: Queue from where to get the messages
    consumer_tag: Consumer identifier
    no_local: Don't receive messages published by this consumer.
    no_ack: Tells the server if the consumer will acknowledge the messages.
    exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
    nowait:
    callback: A PHP Callback
*/

$ch->basic_consume('delayed_queue', '', false, true, false, false, 'process_message');

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
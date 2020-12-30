<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

include(__DIR__ . '/config.php');

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

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
$channel->exchangeDeclare('delayed_exchange', 'x-delayed-message', false, true, false, false, false, new AMQPTable(array(
   "x-delayed-type" => AMQPExchangeType::FANOUT
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
$channel->queueDeclare('delayed_queue', false, false, false, false, false, new AMQPTable(array(
   "x-dead-letter-exchange" => "delayed"
)));

$channel->queueBind('delayed_queue', 'delayed_exchange');

$headers = new AMQPTable(array("x-delay" => 7000));
$message = new AMQPMessage('hello', array('delivery_mode' => 2));
$message->set('application_headers', $headers);
$channel->basicPublish($message, 'delayed_exchange');

function process_message(AMQPMessage $message)
{
    $headers = $message->get('application_headers');
    $nativeData = $headers->getNativeData();
    var_dump($nativeData['x-delay']);
    $message->ack();
}

/*
    queue: Queue from where to get the messages
    consumer_tag: Consumer identifier
    no_local: Don't receive messages published by this consumer.
    no_ack: If set to true, automatic acknowledgement mode will be used by this consumer. See https://www.rabbitmq.com/confirms.html for details.
    exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
    nowait:
    callback: A PHP Callback
*/

$channel->basicConsume('delayed_queue', '', false, false, false, false, 'process_message');

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
while ($channel->isConsuming()) {
    $channel->wait();
}

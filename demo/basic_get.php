<?php

include(__DIR__ . '/config.php');
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

$exchange = 'basic_get_test';
$queue = 'basic_get_queue';

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

/*
    The following code is the same both in the consumer and the producer.
    In this way we are sure we always have a queue to consume from and an
        exchange where to publish messages.
*/

/*
    name: $queue
    passive: false
    durable: true // the queue will survive server restarts
    exclusive: false // the queue can be accessed in other channels
    auto_delete: false //the queue won't be deleted once the channel is closed.
*/
$channel->queue_declare($queue, false, true, false, false);

/*
    name: $exchange
    type: direct
    passive: false
    durable: true // the exchange will survive server restarts
    auto_delete: false //the exchange won't be deleted once the channel is closed.
*/

$channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);

$channel->queue_bind($queue, $exchange);

$toSend = new AMQPMessage('test message', array('content_type' => 'text/plain', 'delivery_mode' => 2));
$channel->basic_publish($toSend, $exchange);

$message = $channel->basic_get($queue);

$channel->basic_ack($message->delivery_info['delivery_tag']);

var_dump($message->body);

$channel->close();
$connection->close();

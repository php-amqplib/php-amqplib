<?php

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

$exchange = 'fanout_exclusive_example_exchange';

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

/*
    name: $exchange
    type: fanout
    passive: false // don't check if an exchange with the same name exists
    durable: false // the exchange won't survive server restarts
    auto_delete: true // the exchange will be deleted once the channel is closed.
*/

$channel->exchange_declare($exchange, AMQPExchangeType::FANOUT, false, false, true);

$messageBody = implode(' ', array_slice($argv, 1));
$message = new AMQPMessage($messageBody, array('content_type' => 'text/plain'));
$channel->basic_publish($message, $exchange);

$channel->close();
$connection->close();

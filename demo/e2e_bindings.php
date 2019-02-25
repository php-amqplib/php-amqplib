<?php

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

$source = 'my_source_exchange';
$destination = 'my_dest_exchange';

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$channel->exchange_declare($source, AMQPExchangeType::TOPIC, false, true, false);

$channel->exchange_declare($destination, AMQPExchangeType::DIRECT, false, true, false);

$channel->exchange_bind($destination, $source);

$channel->exchange_unbind($source, $destination);

$channel->close();
$connection->close();

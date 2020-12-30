<?php

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

$source = 'my_source_exchange';
$destination = 'my_dest_exchange';

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$channel->exchangeDeclare($source, AMQPExchangeType::TOPIC, false, true, false);

$channel->exchangeDeclare($destination, AMQPExchangeType::DIRECT, false, true, false);

$channel->exchangeBind($destination, $source);

$channel->exchangeUnbind($source, $destination);

$channel->close();
$connection->close();

<?php

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

$exchange = 'router';
$queue = 'haqueue';
$specificQueue = 'specific-haqueue';

$consumerTag = 'consumer';

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$channel->queue_declare('test11', false, true, false, false, false, new AMQPTable(array(
   "x-dead-letter-exchange" => "t_test1",
   "x-message-ttl" => 15000,
   "x-expires" => 16000
)));

$channel->close();
$connection->close();

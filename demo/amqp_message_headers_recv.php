<?php
include(__DIR__ . '/config.php');
use PhpAmqpLib\Connection\AMQPConnection;


$binding_keys = array_slice($argv, 1);
if (empty($binding_keys)) {
    file_put_contents('php://stderr', "Usage: $argv[0] [binding_key]\n");
    exit(1);
}


$connection = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$exchName = 'topic_headers_test';
$channel->exchange_declare($exchName, 'topic', $passv = false, $durable = false, $autodel = true);

list($queue_name, ,) = $channel->queue_declare("", $passv = false, $durable = false, $exclusive = true, $autoDel = true);

foreach ($binding_keys as $binding_key) {
    $channel->queue_bind($queue_name, $exchName, $binding_key);
}

echo ' [*] Waiting for logs. To exit press CTRL+C', "\n";

$callback = function ($msg) {
    echo PHP_EOL . ' [x] ', $msg->delivery_info['routing_key'], ':', $msg->body, "\n";
    echo 'Message headers follows' . PHP_EOL;
    var_dump($msg->get('application_headers')->getNativeData());
    echo PHP_EOL;
};

$channel->basic_consume($queue_name, '', $noLocal = false, $noAck = true, $exclusive = true, $noWait = false, $callback);
while (count($channel->callbacks)) {
    $channel->wait();
    echo '*' . PHP_EOL;
}

$channel->close();
$connection->close();

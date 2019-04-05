<?php
include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

$bindingKeys = array_slice($argv, 1);
if (empty($bindingKeys)) {
    file_put_contents('php://stderr', "Usage: $argv[0] [binding_key]\n");
    exit(1);
}


$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$exchangeName = 'topic_headers_test';
$channel->exchange_declare($exchangeName, AMQPExchangeType::TOPIC, false, false, true);

list($queueName, ,) = $channel->queue_declare("", false, false, true, true);

foreach ($bindingKeys as $bindingKey) {
    $channel->queue_bind($queueName, $exchangeName, $bindingKey);
}

echo ' [*] Waiting for logs. To exit press CTRL+C', "\n";

$callback = function (AMQPMessage $message) {
    echo PHP_EOL . ' [x] ', $message->delivery_info['routing_key'], ':', $message->body, "\n";
    echo 'Message headers follows' . PHP_EOL;
    var_dump($message->get('application_headers')->getNativeData());
    echo PHP_EOL;
};

$channel->basic_consume($queueName, '', false, true, true, false, $callback);
while ($channel->is_consuming()) {
    $channel->wait();
    echo '*' . PHP_EOL;
}

$channel->close();
$connection->close();

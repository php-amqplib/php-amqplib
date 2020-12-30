<?php

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

$exchange = 'router';
$queue = 'msgs';
$consumerTag = 'consumer';

/**
 * @var AbstractConnection $connection
 */
$connection = AMQPStreamConnection::createConnection([
    ['host' => HOST, 'port' => PORT, 'user' => USER, 'password' => PASS, 'vhost' => VHOST]
], ['heartbeat' => 4]);

$sender = new PCNTLHeartbeatSender($connection);
$sender->register();

$channel = $connection->channel();

$channel->queueDeclare($queue, false, true, false, false);
$channel->exchangeDeclare($exchange, AMQPExchangeType::DIRECT, false, true, false);
$channel->queueBind($queue, $exchange);

/**
 * @param AMQPMessage $message
 */
function process_message($message)
{
    echo "\n--------\n";
    echo $message->body;
    echo "\n--------\n";
    echo "\nprocessing message\n";

    // Message handling time will be 5x longer than the heartbeat timeout
    $timeLeft = 20;
    while ($timeLeft > 0) {
        $timeLeft = sleep($timeLeft);
    }

    echo "\nmessage processed\n";

    $message->ack();

    // Send a message with the string "quit" to cancel the consumer.
    if ($message->body === 'quit') {
        $message->getChannel()->basicCancel($message->getConsumerTag());
    }
}

$channel->basicConsume($queue, $consumerTag, false, false, false, false, 'process_message');

/**
 * @param \PhpAmqpLib\Channel\AMQPChannel $channel
 * @param AbstractConnection $connection
 */
function shutdown($channel, $connection)
{
    $channel->close();
    $connection->close();
}

register_shutdown_function('shutdown', $channel, $connection);

while ($channel->isConsuming()) {
    $channel->wait();
}

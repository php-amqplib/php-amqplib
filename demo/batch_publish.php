<?php
/**
 * Usage:
 *  php batch_publish.php msg_count batch_size
 * The integer arguments tells the script how many messages to publish.
 */
include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPConnectionBlockedException;

$exchange = 'bench_exchange';
$queue = 'bench_queue';

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$channel->queueDeclare($queue, false, false, false, false);

$channel->exchangeDeclare($exchange, AMQPExchangeType::DIRECT, false, false, false);

$channel->queueBind($queue, $exchange);


$messageBody = <<<EOT
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyza
EOT;

$message = new AMQPMessage($messageBody);

$time = microtime(true);

$max = isset($argv[1]) ? (int) $argv[1] : 1;
$batch = isset($argv[2]) ? (int) $argv[2] : 2;

// Publishes $max messages using $messageBody as the content.
for ($i = 0; $i < $max; $i++) {
    $channel->batchBasicPublish($message, $exchange);

    if ($i % $batch == 0) {
        try {
            $channel->publishBatch();
        } catch (AMQPConnectionBlockedException $exception) {
            do {
                sleep(10);
            } while ($connection->isBlocked());
            $channel->publishBatch();
        }
    }
}

$channel->publishBatch();

echo microtime(true) - $time, "\n";

$channel->basicPublish(new AMQPMessage('quit'), $exchange);

$channel->close();
$connection->close();

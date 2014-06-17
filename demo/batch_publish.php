<?php

/**
 * Usage:
 *  php batch_publish.php msg_count batch_size
 * The integer arguments tells the script how many messages to publish.
 */

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

$exchange = 'bench_exchange';
$queue = 'bench_queue';

$conn = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
$ch = $conn->channel();

$ch->queue_declare($queue, false, false, false, false);

$ch->exchange_declare($exchange, 'direct', false, false, false);

$ch->queue_bind($queue, $exchange);


$msg_body = <<<EOT
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

$msg = new AMQPMessage($msg_body);

$time = microtime(true);

$max = isset($argv[1]) ? (int) $argv[1] : 1;
$batch = isset($argv[2]) ? (int) $argv[2] : 2;

// Publishes $max messages using $msg_body as the content.
for ($i = 0; $i < $max; $i++) {
    $ch->batch_basic_publish($msg, $exchange);

    if ($i % $batch == 0) {
        $ch->publish_batch();
    }
}

$ch->publish_batch();

echo microtime(true) - $time, "\n";

$ch->basic_publish(new AMQPMessage('quit'), $exchange);

$ch->close();
$conn->close();
?>

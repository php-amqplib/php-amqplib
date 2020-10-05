<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/bootstrap.php';

$exception = null;
$timeout = isset($argv[1]) ? (int)$argv[1] : 30;
$start = microtime(true);
$until = $start + $timeout;

do {
    try {
        $connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
        if ($connection->isConnected()) {
            echo 'Broker is ready to accept connections', PHP_EOL;
            die;
        }
    } catch (Exception $exception) {
        echo '.';
        sleep(1);
    }
} while ($until > time());

$end = microtime(true);
if ($until < $end) {
    echo PHP_EOL, sprintf('Broker wait timeout out after %.1f', $end - $start), PHP_EOL;
    if ($exception) {
        echo $exception->getCode(), ':', $exception->getMessage(), PHP_EOL;
    }
    exit(1);
}

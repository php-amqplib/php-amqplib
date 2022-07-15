<?php

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPSSLConnection;

// This is simplest configuration that is needed to connect to Amazon MQ with RabbitMQ engine.
// Amazon MQ requires TLS only for encryption of connection traffic
// and not for peer verification, so disabling "verify_peer" is the only SSL option we need.
// See https://www.rabbitmq.com/ssl.html#certificates-and-keys for more explanations on the topic.
$sslOptions = ['verify_peer' => false];

$connection = new AMQPSSLConnection(HOST, 5671, USER, PASS, VHOST, $sslOptions);

/**
 * @param \PhpAmqpLib\Connection\AbstractConnection $connection
 */
function shutdown($connection)
{
    $connection->close();
}

register_shutdown_function('shutdown', $connection);

while (true) {
    sleep(1);
}

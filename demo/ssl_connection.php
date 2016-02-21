<?php

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPSSLConnection;

define('CERTS_PATH', '/git/rabbitmqinaction/av_scratchwork/openssl');

$sslOptions = array(
    'cafile' => CERTS_PATH . '/rmqca/cacert.pem',
    'local_cert' => CERTS_PATH . '/phpcert.pem',
    'verify_peer' => true
);

$connection = new AMQPSSLConnection(HOST, PORT, USER, PASS, VHOST, $sslOptions);

/**
 * @param \PhpAmqpLib\Connection\AbstractConnection $connection
 */
function shutdown($connection)
{
    $connection->close();
}

register_shutdown_function('shutdown', $connection);

while (true) {
}

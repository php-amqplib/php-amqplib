<?php

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPSSLConnection;

define('CERTS_PATH', realpath(__DIR__ . '/../tests/certs'));

$sslOptions = array(
    'cafile' => CERTS_PATH . '/ca_certificate.pem',
    'local_cert' => CERTS_PATH . '/client_certificate.pem',
    'local_pk' => CERTS_PATH . '/client_key.pem',
    'verify_peer' => true,
    'verify_peer_name' => false,
);

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

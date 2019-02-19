<?php

include(__DIR__ . '/config.php');
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;

define('CERTS_PATH', '/git/rabbitmqinaction/av_scratchwork/openssl');

$sslOptions = array(
    'cafile' => CERTS_PATH . '/rmqca/cacert.pem',
    'local_cert' => CERTS_PATH . '/phpcert.pem',
    'verify_peer' => true
);

/*
    create_connection takes an array of host configurations and an array of options
    It will try connecting to hosts one-by-one and return the first successful
    connection.
    After reaching the end of the array, it will throw the last connection exception.
    Options will be mapped to constructor arguments for used connection type.
*/
$connection = AMQPStreamConnection::create_connection([
    ['host' => HOST, 'port' => PORT, 'user' => USER, 'password' => PASS, 'vhost' => VHOST],
    ['host' => HOST, 'port' => 5673, 'user' => USER, 'password' => PASS, 'vhost' => VHOST],
    ['host' => HOST, 'port' => 5674, 'user' => USER, 'password' => PASS, 'vhost' => VHOST]
],
[
    'insist' => false,
    'login_method' => 'AMQPLAIN',
    'login_response' => null,
    'locale' => 'en_US',
    'connection_timeout' => 3.0,
    'read_write_timeout' => 10.0,
    'context' => null,
    'keepalive' => false,
    'heartbeat' => 5
]);


// Use empty options array for defaults
$connection = AMQPStreamConnection::create_connection([
    ['host' => HOST, 'port' => PORT, 'user' => USER, 'password' => PASS, 'vhost' => VHOST],
    ['host' => HOST, 'port' => 5673, 'user' => USER, 'password' => PASS, 'vhost' => VHOST],
    ['host' => HOST, 'port' => 5674, 'user' => USER, 'password' => PASS, 'vhost' => VHOST]
],
[]);

// Options keys are different for different connection types
$connection = AMQPSocketConnection::create_connection([
    ['host' => HOST, 'port' => PORT, 'user' => USER, 'password' => PASS, 'vhost' => VHOST],
    ['host' => HOST, 'port' => 5673, 'user' => USER, 'password' => PASS, 'vhost' => VHOST],
    ['host' => HOST, 'port' => 5674, 'user' => USER, 'password' => PASS, 'vhost' => VHOST]
],
[
    'insist' => false,
    'login_method' => 'AMQPLAIN',
    'login_response' => null,
    'locale' => 'en_US',
    'read_timeout' => 10,
    'keepalive' => false,
    'write_timeout' => 10,
    'heartbeat' => 5
]);

// Use empty options array for defaults
$connection = AMQPSocketConnection::create_connection([
    ['host' => HOST, 'port' => PORT, 'user' => USER, 'password' => PASS, 'vhost' => VHOST],
    ['host' => HOST, 'port' => 5673, 'user' => USER, 'password' => PASS, 'vhost' => VHOST],
    ['host' => HOST, 'port' => 5674, 'user' => USER, 'password' => PASS, 'vhost' => VHOST]
], []);


/*
    For SSL connections you should set 'ssl_options' in the options array
*/
$ssl_connection = AMQPSSLConnection::create_connection([
    ['host' => HOST, 'port' => PORT, 'user' => USER, 'password' => PASS, 'vhost' => VHOST],
    ['host' => HOST, 'port' => 5673, 'user' => USER, 'password' => PASS, 'vhost' => VHOST],
    ['host' => HOST, 'port' => 5674, 'user' => USER, 'password' => PASS, 'vhost' => VHOST]
],
[
    'ssl_options' => $ssl_options
]);


/**
 * @param \PhpAmqpLib\Connection\AbstractConnection $connection
 */
function shutdown($connection)
{
    $connection->close();
}

register_shutdown_function('shutdown', $connection);
register_shutdown_function('shutdown', $ssl_connection);

while (true) {
}


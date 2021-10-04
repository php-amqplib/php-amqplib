<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

$certsPath = __DIR__ . '/certs';
$options = [
    'cafile' => $certsPath . '/ca_certificate.pem',
    'local_cert' => $certsPath . '/client_certificate.pem',
    'local_pk' => $certsPath . '/client_key.pem',
    'verify_peer' => true,
    'verify_peer_name' => false,
];

$context = stream_context_create();
foreach ($options as $k => $v) {
    stream_context_set_option($context, 'ssl', $k, $v);
}

$socket = stream_socket_client(
    'tlsv1.2://rabbitmq:5671',
    $errno,
    $errstr,
    1,
    STREAM_CLIENT_CONNECT,
    $context
);

var_dump($socket);

<?php

require_once(__DIR__ . '/../amqp.inc');

define('HOST', 'localhost');
define('PORT', 5671);
define('USER', 'guest');
define('PASS', 'guest');
define('VHOST', '/');

//If this is enabled you can see AMQP output on the CLI
define('AMQP_DEBUG', true);

define('CERTS_PATH',
  '/git/rabbitmqinaction/av_scratchwork/openssl');

$ssl_options = array(
      'cafile' => CERTS_PATH . '/rmqca/cacert.pem',
      'local_cert' => CERTS_PATH . '/phpcert.pem',
      'verify_peer' => true
  );

$conn = new AMQPSSLConnection(HOST, PORT, USER, PASS, VHOST, $ssl_options);

function shutdown($conn){
    $conn->close();
}

register_shutdown_function('shutdown', $conn);

while(1){}
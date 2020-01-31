<?php
// include the composer autoloader
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Message\AMQPMessage;

// uncomment this if you need to inspect all AMQP traffic (it's noisy!)
define('AMQP_DEBUG', true);


$HOST = 'somehost.com';
$PORT = '5671';
$USERNAME = 'guest';
$PASSWORD = 'guest';
$VHOST = '/';

// $SSL_OPTIONS is used to created the ssl context 
// https://www.php.net/manual/en/context.ssl.php
// https://github.com/php-amqplib/php-amqplib/blob/master/demo/amqp_consumer_signals.php - good example. 
// demo/ssl_connection.php - good example.
$SSL_OPTIONS = array(
	'verify_peer' => false,
	'verify_peer_name' => false,
);

// https://github.com/php-amqplib/RabbitMqBundle/issues/356
// https://github.com/php-amqplib/php-amqplib/blob/master/demo/amqp_consumer_signals.php
// These are all the options in /PhpAmqpLib/Connection/AMQPSSLConnection.php so you can review. 
// Everything not commented is the default. 
$OPTIONS = array(
	'insist'              => false,
	'login_method'        => 'AMQPLAIN',
	'login_response'      => null,
	'locale'              => 'en_US',
	'connection_timeout'  => 3,
	'channel_rpc_timeout' => 0.0,
	'read_write_timeout'  => 30,    // needs to be at least 2x heartbeat - default is 130 
	'keepalive'           => false, // doesn't work with ssl connections - default is false 
	'heartbeat'           => 15,    // default is 0 
);

$SSL_PROTOCOL = 'ssl'; // This is the default and is the last positional argument in AMQPSSLConnection(). 

// /PhpAmqpLib/Connection/AMQPSSLConnection.php
$CONNECTION = new AMQPSSLConnection($HOST,$PORT,$USERNAME,$PASSWORD,$VHOST,$SSL_OPTIONS,$OPTIONS,$SSL_PROTOCOL);
$CHANNEL = $CONNECTION->channel();


// /PhpAmqpLib/Channel/AMQPChannel.php
// routing_key is the third positional argument for basic_publish and batch_basic_publish not queue name   
$EXCHANGE = 'exchange_name';
$ROUTING_KEY = 'routing_key';

$MSG_BODY = '{"Massage":"Hello World!!"}';

// Make the message 
// /PhpAmqpLib/Message/AMQPMessage.php - more options here..
$MSG = new AMQPMessage($MSG_BODY, array('content_type' => 'text/plain', 'delivery_mode' => 2));

// Send the Message 
$CHANNEL->basic_publish($MSG, $EXCHANGE, $ROUTING_KEY);

?>

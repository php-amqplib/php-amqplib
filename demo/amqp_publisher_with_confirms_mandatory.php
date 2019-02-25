<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

include(__DIR__ . '/config.php');

$exchange = 'someExchange';

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$channel->set_ack_handler(
    function (AMQPMessage $message) {
        echo "Message acked with content " . $message->body . PHP_EOL;
    }
);

$channel->set_nack_handler(
    function (AMQPMessage $message) {
        echo "Message nacked with content " . $message->body . PHP_EOL;
    }
);

$channel->set_return_listener(
    function ($replyCode, $replyText, $exchange, $routingKey, AMQPMessage $message) {
        echo "Message returned with content " . $message->body . PHP_EOL;
    }
);

/*
 * bring the channel into publish confirm mode.
 * if you would call $ch->tx_select() befor or after you brought the channel into this mode
 * the next call to $ch->wait() would result in an exception as the publish confirm mode and transactions
 * are mutually exclusive
 */
$channel->confirm_select();

/*
    name: $exchange
    type: fanout
    passive: false // don't check if an exchange with the same name exists
    durable: false // the exchange won't survive server restarts
    auto_delete: true //the exchange will be deleted once the channel is closed.
*/

$channel->exchange_declare($exchange, AMQPExchangeType::FANOUT, false, false, true);

$i = 1;
$message = new AMQPMessage($i, array('content_type' => 'text/plain'));
$channel->basic_publish($message, $exchange, null, true);

/*
 * watching the amqp debug output you can see that the server will ack the message with delivery tag 1 and the
 * multiple flag probably set to false
 */

$channel->wait_for_pending_acks_returns();

while ($i <= 11) {
    $message = new AMQPMessage($i++, array('content_type' => 'text/plain'));
    $channel->basic_publish($message, $exchange, null, true);
}

/*
 * you do not have to wait for pending acks after each message sent. in fact it will be much more efficient
 * to wait for as many messages to be acked as possible.
 */
$channel->wait_for_pending_acks_returns();

$channel->close();
$connection->close();

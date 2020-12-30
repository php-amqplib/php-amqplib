<?php

require __DIR__ . '/config.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire;

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$exchangeName = 'topic_headers_test';
$channel->exchangeDeclare($exchangeName, AMQPExchangeType::HEADERS);

$data = implode(' ', array_slice($argv, 2));
if (empty($data)) {
    $data = "Hello World!";
}

$message = new AMQPMessage($data);
$headers = new Wire\AMQPTable(array(
    'foo' => 'bar',
    'table' => array('figuf', 'ghf' => 5, 5 => 675),
    'num1' => -4294967295,
    'num2' => 5,
    'num3' => -2147483648,
    'true' => true,
    'false' => false,
    'void' => null,
    'date' => new DateTime('now', new DateTimeZone('UTC')),
    'array' => array(null, 'foo', 'bar', 5, 5674625, 'ttt', array(5, 8, 2)),
    'arr_with_tbl' => array(
        'bar',
        5,
        array(
            'foo',
            57,
            'ee',
            array(
                'foo' => 'bar',
                'baz' => 'boo',
                'arr' => array(1, 2, 3, true, new DateTime('now', new DateTimeZone('UTC'))),
            ),
        ),
        67,
        array(
            'foo' => 'bar',
            5 => 7,
            8 => 'boo',
            'baz' => 3,
        ),
    ),
    '64bitint' => 9223372036854775807,
    '64bit_uint' => '18446744073709600000',
    '64bitint_neg' => -pow(2, 40),
));
$headers->set('shortshort', -5, Wire\AMQPTable::T_INT_SHORTSHORT);
$headers->set('short', -1024, Wire\AMQPTable::T_INT_SHORT);

echo PHP_EOL . PHP_EOL . 'SENDING MESSAGE WITH HEADERS' . PHP_EOL . PHP_EOL;
var_dump($headers->getNativeData());
echo PHP_EOL;

$message->set('application_headers', $headers);
$channel->basicPublish($message, $exchangeName);

echo ' [x] Sent :', $data, PHP_EOL;

$channel->close();
$connection->close();

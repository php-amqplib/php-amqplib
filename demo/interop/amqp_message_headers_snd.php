<?php

use Interop\Amqp\AmqpTopic;
use PhpAmqpLib\Interop\AmqpConnectionFactory;

include(__DIR__ . '/config.php');

$exchangeName = 'topic_headers_test';

$context = (new AmqpConnectionFactory(AMQP_DSN))->createContext();

$topic = $context->createTopic($exchangeName);
$topic->setType(AmqpTopic::TYPE_TOPIC);
$topic->addFlag(AmqpTopic::FLAG_AUTODELETE);
$context->declareTopic($topic);

$routingKey = empty($argv[1]) ? '' : $argv[1];
$data = implode(' ', array_slice($argv, 2));
if (empty($data)) {
    $data = "Hello World!";
}

$routingKey = empty($argv[1]) ? '' : $argv[1];
$data = implode(' ', array_slice($argv, 2));
if (empty($data)) {
    $data = "Hello World!";
}

$message = $context->createMessage($data, array(
    'x-foo'=>'bar',
    'table'=>array('figuf', 'ghf'=>5, 5=>675),
    'num1' => -4294967295,
    'num2' => 5,
    'num3' => -2147483648,
    'true' => true,
    'false' => false,
    'void' => null,
    'date' => new DateTime(),
    'array' => array(null, 'foo', 'bar', 5, 5674625, 'ttt', array(5, 8, 2)),
    'arr_with_tbl' => array(
        'bar',
        5,
        array('foo', 57, 'ee', array('foo'=>'bar', 'baz'=>'boo', 'arr'=>array(1,2,3, true, new DateTime()))),
        67,
        array(
            'foo'=>'bar',
            5=>7,
            8=>'boo',
            'baz'=>3
        )
    ),
    '64bitint' => 9223372036854775807,
    '64bit_uint' => '18446744073709600000',
    '64bitint_neg' => -pow(2, 40)
));
$message->setProperty('shortshort', -5);
$message->setProperty('short', -1024);
$message->setRoutingKey($routingKey);

echo PHP_EOL . PHP_EOL . 'SENDING MESSAGE WITH HEADERS' . PHP_EOL . PHP_EOL;
var_dump($message->getProperties());
echo PHP_EOL;

$context->createProducer()->send($topic, $message);

echo " [x] Sent ", $routingKey, ':', $data, " \n";

$context->close();

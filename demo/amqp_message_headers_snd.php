<?php
include(__DIR__ . '/config.php');
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire;


$connection = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$exchName = 'topic_headers_test';
$channel->exchange_declare($exchName, 'topic', $passv = false, $durable = false, $autodel = true);

$routing_key = empty($argv[1]) ? '' : $argv[1];
$data = implode(' ', array_slice($argv, 2));
if (empty($data)) {
    $data = "Hello World!";
}

$msg = new AMQPMessage($data);
$hdrs=new Wire\AMQPTable(array(
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
$hdrs->set('shortshort', -5, Wire\AMQPTable::T_INT_SHORTSHORT);
$hdrs->set('short', -1024, Wire\AMQPTable::T_INT_SHORT);

echo PHP_EOL . PHP_EOL . 'SENDING MESSAGE WITH HEADERS' . PHP_EOL . PHP_EOL;
var_dump($hdrs->getNativeData());
echo PHP_EOL;

$msg->set('application_headers', $hdrs);
$channel->basic_publish($msg, $exchName, $routing_key);

echo " [x] Sent ", $routing_key, ':', $data, " \n";

$channel->close();
$connection->close();

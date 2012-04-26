<?php

//AMQP PHP library test
/* note: unfortunately this example is using non-OOP terminology
         since the basic_consume function makes only procedural call possible.
 */

include(__DIR__ . '/../config.php');
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

$response = false;

function on_response($msg)
{
    global $corr_id,$response;
    if ($corr_id == $msg->properties['correlation_id'])
        $response = $msg->body;
}

function call($n)
{
    global $corr_id,$response,$ch,$callback_queue;
    $corr_id = uniqid();
    $response = false;

    $message = new AMQPMessage($n,array("correlation_id" => $corr_id,"reply_to" => $callback_queue));

    $ch->basic_publish($message,"","rpc_queue");

    while($response === false)
    {
        $ch->wait();
    }

 }

try
{
    echo "Creating connection\n";
    $conn = new AMQPConnection(HOST, PORT,
                               USER,
                               PASS);

    echo "Getting channel\n";
    $ch = $conn->channel();

    echo "Requesting access\n";
    $ch->access_request('/data', false, false, true, true);

    echo "Declaring queue\n";
    $queue = $ch->queue_declare("",false,false,true);

    $callback_queue = $queue[0];

    $corr_id = Null;

    $ch->basic_consume($callback_queue,"",false,true,false,false,"on_response");

    echo "Requesting fib(30)\n";
    call(30);

    echo "Got {$response}\n";

    echo "Closing channel\n";
    $ch->close();

    echo "Closing connection\n";
    $conn->close();

    echo "Done.\n";
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage();
    echo "\nTrace:\n" . $e->getTraceAsString();
}

?>
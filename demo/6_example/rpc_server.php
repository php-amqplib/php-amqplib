<?php

//AMQP PHP library test

include(__DIR__ . '/../config.php');
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

function fib($n)
{
    if ($n == 0)
        return 0;
    elseif ($n == 1)
        return 1;
    else
        return fib($n-1) + fib($n-2);
}

function on_request($msg)
{
    $n = (int) $msg->body;
    echo " [.] fib({$n})\n";
    $response = new AMQPMessage(fib($n),array("correlation_id" => $msg->properties['correlation_id']));
    $msg->delivery_info['channel']->basic_publish($response,"",$msg->properties['reply_to']);
    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
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
    $ch->queue_declare("rpc_queue");

    $ch->basic_qos(0,1,false);

    echo "Consuming message\n";
    $ch->basic_consume("rpc_queue","",false,false,false,false,"on_request");

    while(True)
    {
        $ch->wait();
    }

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
<?php

//AMQP PHP library test

include(__DIR__ . '/../config.php');
use PhpAmqpLib\Connection\AMQPConnection;

$msg_body = NULL;

function callback($msg)
{
  echo "Message: " . $msg->body . "\n";
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
    $queue = $ch->queue_declare("hello");

    echo "Consuming message\n";
    $ch->basic_consume("hello","testtag",false,true,false,true,"callback");

    
    while(True)
    {
        $ch->wait();
    }
    
    $ch->basic_cancel("testtag");
    
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

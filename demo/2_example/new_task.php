<?php

//AMQP PHP library test

include(__DIR__ . '/../config.php');
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

if ($argc < 2)
{
    die("You must specify a task like 'Hello...' means a 3rd level complex task.\n");
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
    $queue = $ch->queue_declare("task_queue",false,true);

    echo "Creating message\n";
    $msg = new AMQPMessage($argv[1], array('content_type' => 'text/plain','delivery_mode'=>2));

    echo "Publishing message\n";
    $ch->basic_publish($msg,"","task_queue");

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
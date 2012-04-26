<?php

//AMQP PHP library test

include(__DIR__ . '/../config.php');
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

if ($argc < 3)
{
    die("You must specify <facility>.<severity> and the message.\n");
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
    
    echo "Declaring exchange\n";
    $ch->exchange_declare("topic_logs","topic");

    echo "Creating message\n";
    $msg = new AMQPMessage($argv[2]);

    echo "Publishing message\n";
    $ch->basic_publish($msg,"topic_logs",$argv[1]);

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
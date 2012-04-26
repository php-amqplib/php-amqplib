<?php

//AMQP PHP library test

include(__DIR__ . '/../config.php');
use PhpAmqpLib\Connection\AMQPConnection;

if ($argc < 2)
{
    die("You must specify at least one severity as an argument.\n");
}

$severities = $argv;
array_shift($severities);

function callback($msg)
{
  echo "Message: " . $msg->body ."\n";
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
    $ch->exchange_declare("direct_logs","direct");

    echo "Declaring queue\n";
    $queue = $ch->queue_declare("",false,false,true);

    echo "Binding queue\n";
    foreach($severities as $severity)
        $ch->queue_bind($queue[0],"direct_logs",$severity);

    echo "Consuming message\n";
    $ch->basic_consume($queue[0],"",false,true,false,false,"callback");

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

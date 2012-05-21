<?php

//AMQP PHP library test

include(__DIR__ . '/../config.php');
use PhpAmqpLib\Connection\AMQPConnection;

//function callback($msg)
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
    $ch->exchange_declare("logs","fanout");

    echo "Declaring queue\n";
    $queue = $ch->queue_declare("",false,false,true);

    echo "Consuming message\n";
    $ch->basic_consume($queue[0],"",false,true,false,false,"callback");

    $ch->queue_bind($queue[0],"logs");

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

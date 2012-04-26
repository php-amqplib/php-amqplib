<?php

//AMQP PHP library test

include(__DIR__ . '/../config.php');
use PhpAmqpLib\Connection\AMQPConnection;

if ($argc < 2)
{
    die("You must specify at least one <facility>.<severity> as an argument.\n");
}

$binding_keys = $argv;
array_shift($binding_keys);

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
    $ch->exchange_declare("topic_logs","topic");

    echo "Declaring queue\n";
    $queue = $ch->queue_declare("",false,false,true);

    echo "Binding queue\n";
    foreach($binding_keys as $binding_key)
        $ch->queue_bind($queue[0],"topic_logs",$binding_key);

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

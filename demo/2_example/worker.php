<?php

//AMQP PHP library test

include(__DIR__ . '/../config.php');
use PhpAmqpLib\Connection\AMQPConnection;

//function callback($msg)
function callback($msg)
{
  echo "Task: " . $msg->body . " received... working...";
  usleep(1000000 * substr_count($msg->body,'.'));
  echo "DONE.\n";
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
    $queue = $ch->queue_declare("task_queue",false,true);


    $ch->basic_qos(0,1,false);
    
    /*
    Here it is quite important that the routing_key is with the same name as the declared queue name.
    */
    echo "Consuming message\n";
    $ch->basic_consume("task_queue","testtag",false,false,false,true,"callback");

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

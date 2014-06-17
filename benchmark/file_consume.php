<?php

/**
 * Usage: php file_consume.php 100
 */

use PhpAmqpLib\Connection\AMQPConnection;

require_once __DIR__ . '/config.php';

$exchange = 'file_exchange';
$queue = 'file_queue';
$consumer_tag = '';

$conn = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
$ch = $conn->channel();

$ch->queue_declare($queue, false, false, false, false);
$ch->exchange_declare($exchange, 'direct', false, false, false);
$ch->queue_bind($queue, $exchange);



class FileConsumer
{

    protected $msgCount = 0;

    protected $startTime = null;



    /**
     * @param \PhpAmqpLib\Message\AMQPMessage $msg
     */
    public function process_message($msg)
    {
        if ($this->startTime === null) {
            $this->startTime = microtime(true);
        }

        if ($msg->body == 'quit') {
            echo sprintf("Pid: %s, Count: %s, Time: %.4f\n", getmypid(), $this->msgCount, microtime(true) - $this->startTime);
            die;
        }
        $this->msgCount++;
    }
}



$ch->basic_consume($queue, '', false, true, false, false, array(new FileConsumer(), 'process_message'));

function shutdown($ch, $conn)
{
    $ch->close();
    $conn->close();
}

register_shutdown_function('shutdown', $ch, $conn);

while (count($ch->callbacks)) {
    $ch->wait();
}

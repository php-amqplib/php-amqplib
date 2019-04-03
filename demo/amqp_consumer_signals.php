<?php

use PhpAmqpLib\Exchange\AMQPExchangeType;

include(__DIR__ . '/config.php');

/**
 * This class shows how you can use signals to handle consumers
 *
 */
class Consumer
{
    /**
     * Setup signals and connection
     */
    public function __construct()
    {
        if (extension_loaded('pcntl')) {
            define('AMQP_WITHOUT_SIGNALS', false);

            pcntl_signal(SIGTERM, [$this, 'signalHandler']);
            pcntl_signal(SIGHUP, [$this, 'signalHandler']);
            pcntl_signal(SIGINT, [$this, 'signalHandler']);
            pcntl_signal(SIGQUIT, [$this, 'signalHandler']);
            pcntl_signal(SIGUSR1, [$this, 'signalHandler']);
            pcntl_signal(SIGUSR2, [$this, 'signalHandler']);
            pcntl_signal(SIGALRM, [$this, 'alarmHandler']);
        } else {
             echo 'Unable to process signals.' . PHP_EOL;
             exit(1);
        }

        $ssl = null;
        if (PORT === 5671) {
            $ssl = [
                'verify_peer'      => false,
                'verify_peer_name' => false
            ];
        }
        $this->connection = new PhpAmqpLib\Connection\AMQPSSLConnection(
            HOST,
            PORT,
            USER,
            PASS,
            VHOST,
            $ssl,
            [
                'read_write_timeout' => 30,    // needs to be at least 2x heartbeat
                'keepalive'          => false, // doesn't work with ssl connections
                'heartbeat'          => 15
            ]
        );
    }

    /**
     * Signal handler
     *
     * @param  int $signalNumber
     * @return void
     */
    public function signalHandler($signalNumber)
    {
        echo 'Handling signal: #' . $signalNumber . PHP_EOL;
        global $consumer;

        switch ($signalNumber) {
            case SIGTERM:  // 15 : supervisor default stop
            case SIGQUIT:  // 3  : kill -s QUIT
                $consumer->stopHard();
                break;
            case SIGINT:   // 2  : ctrl+c
                $consumer->stop();
                break;
            case SIGHUP:   // 1  : kill -s HUP
                $consumer->restart();
                break;
            case SIGUSR1:  // 10 : kill -s USR1
                // send an alarm in 1 second
                pcntl_alarm(1);
                break;
            case SIGUSR2:  // 12 : kill -s USR2
                // send an alarm in 10 seconds
                pcntl_alarm(10);
                break;
            default:
                break;
        }
    }

    /**
     * Alarm handler
     *
     * @param  int $signalNumber
     * @return void
     */
    public function alarmHandler($signalNumber)
    {
        echo 'Handling alarm: #' . $signalNumber . PHP_EOL;

        echo memory_get_usage(true) . PHP_EOL;
        return;
    }

    /**
     * Message handler
     *
     * @param  PhpAmqpLib\Message\AMQPMessage $message
     * @return void
     */
    public function messageHandler(PhpAmqpLib\Message\AMQPMessage $message)
    {
        echo "\n--------\n";
        echo $message->body;
        echo "\n--------\n";

        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
        if ($message->body === 'quit') {
            $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
        }
    }

    /**
     * Start a consumer on an existing connection
     *
     * @return void
     */
    public function start()
    {
        if ($this->restart) {
            echo 'Restarting consumer.' . PHP_EOL;
            $this->restart = false;
        } else {
            echo 'Starting consumer.' . PHP_EOL;
        }

        $exchange = 'router';
        $queue    = 'msgs';

        $this->channel = $this->connection->channel();
        $this->channel->queue_declare($queue, false, true, false, false);
        $this->channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
        $this->channel->queue_bind($queue, $exchange);
        $this->channel->basic_consume(
            $queue,
            $this->consumerTag,
            false,
            false,
            false,
            false,
            [$this,'messageHandler'],
            null,
            ['x-cancel-on-ha-failover' => ['t', true]] // fail over to another node
        );

        echo 'Enter wait.' . PHP_EOL;
        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
        echo 'Exit wait.' . PHP_EOL;
    }

    /**
     * Restart the consumer on an existing connection
     */
    public function restart()
    {
        $this->stopSoft();
        $this->restart = true;
    }

    /**
     * Close the connection to the server
     */
    public function stopHard()
    {
        echo 'Stopping consumer by closing connection.' . PHP_EOL;
        $this->connection->close();
    }

    /**
     * Close the channel to the server
     */
    public function stopSoft()
    {
        echo 'Stopping consumer by closing channel.' . PHP_EOL;
        $this->channel->close();
    }

    /**
     * Tell the server you are going to stop consuming
     * It will finish up the last message and not send you any more
     */
    public function stop()
    {
        echo 'Stopping consumer by cancel command.' . PHP_EOL;
        // this gets stuck and will not exit without the last two parameters set
        $this->channel->basic_cancel($this->consumerTag, false, true);
    }

    public function shouldRestart()
    {
        return $this->restart;
    }

    /**
     * Current connection
     *
     * @var PhpAmqpLib\Connection\AMQPSSLConnection
     */
    protected $connection = null;

    /**
     * Current channel
     *
     * @var PhpAmqpLib\Channel\AMQPChannel
     */
    protected $channel = null;

    /**
     * Consumer tag
     *
     * @var string
     */
    protected $consumerTag = 'consumer';

    /**
     * @var bool
     */
    protected $restart = false;
}

$consumer = new Consumer();
do {
    $consumer->start();
} while ($consumer->shouldRestart());

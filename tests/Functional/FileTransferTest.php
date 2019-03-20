<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;

class FileTransferTest extends TestCase
{
    protected $exchangeName = 'test_exchange';

    protected $queueName = null;

    protected $connection;

    protected $channel;

    protected $messageBody;

    public function setUp()
    {
        $this->connection = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare($this->exchangeName, 'direct', false, false, false);
        list($this->queueName, ,) = $this->channel->queue_declare();
        $this->channel->queue_bind($this->queueName, $this->exchangeName, $this->queueName);
    }

    public function tearDown()
    {
        if ($this->channel) {
            $this->channel->exchange_delete($this->exchangeName);
            $this->channel->close();
            $this->channel = null;
        }
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * @test
     */
    public function send_file()
    {
        $this->messageBody = $this->generateRandomBytes(1024 * 1024);

        $msg = new AMQPMessage($this->messageBody, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]);

        $this->channel->basic_publish($msg, $this->exchangeName, $this->queueName);

        $this->channel->basic_consume(
            $this->queueName,
            '',
            false,
            false,
            false,
            false,
            [$this, 'processMessage']
        );

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    public function processMessage($msg)
    {
        $delivery_info = $msg->delivery_info;
        $delivery_info['channel']->basic_ack($delivery_info['delivery_tag']);
        $delivery_info['channel']->basic_cancel($delivery_info['consumer_tag']);

        $this->assertEquals($this->messageBody, $msg->body);
    }

    private function generateRandomBytes($num_bytes)
    {
        // If random_bytes exists (PHP 7) or has been polyfilled, use it
        if ( function_exists('random_bytes') ) {
            return random_bytes($num_bytes);
        }
        // Otherwise, just make some noise quickly
        else {
            $data = '';
            for ($i = 0; $i < $num_bytes; $i++) {
                $data .= chr(rand(0, 255));
            }

            return $data;
        }
    }
}

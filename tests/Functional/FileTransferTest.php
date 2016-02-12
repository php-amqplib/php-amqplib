<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class FileTransferTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected $exchangeName = 'test_exchange';

    /**
     * @var string
     */
    protected $queueName = null;

    /**
     * @var AMQPConnection
     */
    protected $connection;

    /**
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * @var string
     */
    protected $messageBody;

    public function setUp()
    {
        $this->connection = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
        $this->channel = $this->connection->channel();

        $this->channel->exchange_declare($this->exchangeName, 'direct', false, false, false);
        list($this->queueName, ,) = $this->channel->queue_declare();
        $this->channel->queue_bind($this->queueName, $this->exchangeName, $this->queueName);
    }

    public function testSendFile()
    {
        $this->messageBody = file_get_contents(__DIR__ . '/fixtures/data_1mb.bin');

        $msg = new AMQPMessage($this->messageBody, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT));

        $this->channel->basic_publish($msg, $this->exchangeName, $this->queueName);

        $this->channel->basic_consume(
            $this->queueName,
            '',
            false,
            false,
            false,
            false,
            array($this, 'processMessage')
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

    public function tearDown()
    {
        if ($this->channel) {
            $this->channel->exchange_delete($this->exchangeName);
            $this->channel->close();
        }

        if ($this->connection) {
            $this->connection->close();
        }
    }
}

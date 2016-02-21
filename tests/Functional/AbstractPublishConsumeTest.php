<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

abstract class AbstractPublishConsumeTest extends \PHPUnit_Framework_TestCase
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
     * @var string
     */
    protected $msgBody;

    /**
     * @var AMQPStreamConnection|AMQPSocketConnection
     */
    protected $connection;

    /**
     * @var AMQPChannel
     */
    protected $channel;

    public function setUp()
    {
        $this->connection = $this->createConnection();
        $this->channel = $this->connection->channel();

        $this->channel->exchange_declare($this->exchangeName, 'direct', false, false, false);
        list($this->queueName, ,) = $this->channel->queue_declare();
        $this->channel->queue_bind($this->queueName, $this->exchangeName, $this->queueName);
    }

    abstract protected function createConnection();

    public function testPublishConsume()
    {
        $this->msgBody = 'foo bar baz äëïöü';

        $msg = new AMQPMessage($this->msgBody, array(
            'content_type' => 'text/plain',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT,
            'correlation_id' => 'my_correlation_id',
            'reply_to' => 'my_reply_to'
        ));

        $this->channel->basic_publish($msg, $this->exchangeName, $this->queueName);

        $this->channel->basic_consume(
            $this->queueName,
            getmypid(),
            false,
            false,
            false,
            false,
            array($this, 'process_msg')
        );

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    public function testPublishPacketSizeLongMessage()
    {
        // Connection frame_max;
        $frame_max = 131072;

        // Publish 3 messages with sizes: packet size - 1, equal packet size and packet size + 1
        for ($length = $frame_max - 9; $length <= $frame_max - 7; $length++) {
            $this->msgBody = str_repeat('1', $length);

            $msg = new AMQPMessage($this->msgBody, array(
                'content_type' => 'text/plain',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT,
                'correlation_id' => 'my_correlation_id',
                'reply_to' => 'my_reply_to'
            ));

            $this->channel->basic_publish($msg, $this->exchangeName, $this->queueName);
        }
    }

    public function process_msg($msg)
    {
        $delivery_info = $msg->delivery_info;

        $delivery_info['channel']->basic_ack($delivery_info['delivery_tag']);
        $delivery_info['channel']->basic_cancel($delivery_info['consumer_tag']);

        $this->assertEquals($this->msgBody, $msg->body);

        //delivery tests
        $this->assertEquals(getmypid(), $delivery_info['consumer_tag']);
        $this->assertEquals($this->queueName, $delivery_info['routing_key']);
        $this->assertEquals($this->exchangeName, $delivery_info['exchange']);
        $this->assertEquals(false, $delivery_info['redelivered']);

        //msg property tests
        $this->assertEquals('text/plain', $msg->get('content_type'));
        $this->assertEquals('my_correlation_id', $msg->get('correlation_id'));
        $this->assertEquals('my_reply_to', $msg->get('reply_to'));

        $this->setExpectedException('OutOfBoundsException');
        $msg->get('no_property');
    }

    public function tearDown()
    {
        if ($this->channel) {
            $this->channel->exchange_delete($this->exchangeName);
            $this->channel->queue_delete($this->queueName);
            $this->channel->close();
        }

        if ($this->connection) {
            $this->connection->close();
        }
    }
}

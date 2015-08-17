<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class Bug256Test extends \PHPUnit_Framework_TestCase
{

    protected $exchange_name = 'test_exchange';

    protected $queue_name = null;

    protected $msg_count = 100;

    protected $consumed_count = 0;

    /**
     * @var AMQPConnection
     */
    protected $conn;

    /**
     * @var AMQPConnection
     */
    protected $conn2;

    /**
     * @var AMQPChannel
     */
    protected $ch;

    /**
     * @var AMQPChannel
     */
    protected $ch2;

    public function setUp()
    {
        $this->conn = new AMQPSocketConnection(HOST, PORT, USER, PASS, VHOST);
        $this->ch = $this->conn->channel();

        $this->ch->exchange_declare($this->exchange_name, 'direct', false, true, false);

        $this->conn2 = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
        $this->ch2 = $this->conn->channel();

        list($this->queue_name, ,) = $this->ch2->queue_declare();
        $this->ch2->queue_bind($this->queue_name, $this->exchange_name, $this->queue_name);
    }

    public function testFrameOrder()
    {
        $msg = new AMQPMessage('');
        $hdrs = new AMQPTable(array('x-foo'=>'bar'));
        $msg->set('application_headers', $hdrs);

        for ($i = 0; $i < $this->msg_count; $i++) {
            $this->ch->basic_publish($msg, $this->exchange_name, $this->queue_name);
        }

        $this->ch2->basic_consume(
            $this->queue_name,
            '',
            false,
            true,
            false,
            false,
            array($this, 'process_msg')
        );

        while (count($this->ch2->callbacks)) {
            $this->ch2->wait();
        }
    }

    public function process_msg($msg)
    {
        $this->consumed_count++;

        $this->assertEquals(array('x-foo'=>'bar'), $msg->get('application_headers')->getNativeData());

        if ($this->consumed_count >= $this->msg_count) {
            $delivery_info = $msg->delivery_info;
            $delivery_info['channel']->basic_cancel($delivery_info['consumer_tag']);
        }
    }

    public function tearDown()
    {
        if ($this->ch) {
            $this->ch->exchange_delete($this->exchange_name);
            $this->ch->close();
        }

        if ($this->conn) {
            $this->conn->close();
        }

        if ($this->ch2) {
            $this->ch2->close();
        }

        if ($this->conn2) {
            $this->conn2->close();
        }
    }
}

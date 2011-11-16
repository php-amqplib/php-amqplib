<?php

include(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../../amqp.inc');

class PublishConsumeTest extends PHPUnit_Framework_TestCase
{
    protected $exchange_name = 'test_exchange';
    protected $queue_name = null;

    public function setUp()
    {


        $this->conn = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
        $this->ch = $this->conn->channel();

        $this->ch->exchange_declare($this->exchange_name, 'direct', false, false, false);
        list($this->queue_name,,) = $this->ch->queue_declare();
        $this->ch->queue_bind($this->queue_name, $this->exchange_name, $this->queue_name);
    }

    public function testPublishConsume()
    {
        $this->msg_body = 'foo bar baz äëïöü';
        $msg = new AMQPMessage($this->msg_body, array(
            'content_type' => 'text/plain',
            'delivery-mode' => 1
        ));

        $this->ch->basic_publish($msg, $this->exchange_name, $this->queue_name);

        $this->ch->basic_consume($this->queue_name, getmypid(), false, false, false, false, array($this, 'process_msg'));

        while(count($this->ch->callbacks)) {
            $this->ch->wait();
        }
    }

    public function process_msg($msg) {
        $delivery_info = $msg->delivery_info;

        $delivery_info['channel']->basic_ack($delivery_info['delivery_tag']);
        $delivery_info['channel']->basic_cancel($delivery_info['consumer_tag']);

        $this->assertEquals($this->msg_body, $msg->body);

        $this->assertEquals(getmypid(), $delivery_info['consumer_tag']);
        $this->assertEquals($this->queue_name, $delivery_info['routing_key']);
        $this->assertEquals($this->exchange_name, $delivery_info['exchange']);
        $this->assertEquals(false, $delivery_info['redelivered']);
    }

    public function tearDown()
    {
        $this->ch->close();
        $this->conn->close();
    }
}
<?php
/**
 * User: Eric Berg
 * Date: 11/12/13
 * Time: 9:30 PM
 */

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ConnectionReconnectTest extends \PHPUnit_Framework_TestCase
{
	protected $exchange_name = 'reconnect_exchange';
	protected $queue_name = 'reconnect_queue';
	protected $message_body1 = 'reconnect body1';
	protected $message_body2 = 'reconnect body1';

	public function setUp()
	{
		$this->conn = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
		$this->ch = $this->conn->channel();

		$this->ch->exchange_declare($this->exchange_name, 'direct', false, false, false);
		list($this->queue_name,,) = $this->ch->queue_declare();
		$this->ch->queue_bind($this->queue_name, $this->exchange_name, $this->queue_name);
	}

	/**
	 * Publish then get a message with the original setup channel, reconnect and then publish/consume again
	 */
	public function testReconnect()
	{
		$msg = new AMQPMessage($this->message_body1, array('delivery_mode' => 1));
		$this->ch->basic_publish($msg, $this->exchange_name, $this->queue_name);
		$msg = $this->ch->basic_get($this->queue_name);

		$this->assertEquals($this->message_body1, $msg->body);

		// Reconnect the channel
		$this->conn->reconnect();

		// Setup new channel
		$this->ch = $this->conn->channel();

		// New message and another publish, then get the message
		$msg = new AMQPMessage($this->message_body2, array('delivery_mode' => 1));
		$this->ch->basic_publish($msg, $this->exchange_name, $this->queue_name);
		$msg = $this->ch->basic_get($this->queue_name);

		$this->assertEquals($this->message_body2, $msg->body);
	}

	public function process_msg($msg)
	{
		$delivery_info = $msg->delivery_info;

		$delivery_info['channel']->basic_ack($delivery_info['delivery_tag']);
		$delivery_info['channel']->basic_cancel($delivery_info['consumer_tag']);

		$this->assertEquals($this->msg_body, $msg->body);
	}
}
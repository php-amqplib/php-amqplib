<?php

namespace PhpAmqpLib\Tests\Functional\Channel;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * @group connection
 */
class HeadersExchangeTest extends ChannelTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->exchange->name = 'amq.headers';
    }

    /**
     * @test
     * @small
     * @covers \PhpAmqpLib\Channel\AMQPChannel::queueBind()
     * @covers \PhpAmqpLib\Exchange\AMQPExchangeType
     */
    public function consume_specific_headers()
    {
        list($queue1) = $this->channel->queueDeclare();
        $this->channel->queueBind($queue1, $this->exchange->name);

        $bindArguments = [
            'foo' => 'bar',
        ];
        list($queue2) = $this->channel->queueDeclare();
        $this->channel->queueBind($queue2, $this->exchange->name, '', false, new AMQPTable($bindArguments));

        // publish message without headers - should appear in 1st queue without filters
        $message = new AMQPMessage('test');
        $this->channel->basicPublish($message, $this->exchange->name);

        $received1 = $this->channel->basicGet($queue1, true);
        $received2 = $this->channel->basicGet($queue2, true);

        $this->assertInstanceOf(AMQPMessage::class, $received1);
        $this->assertNull($received2);

        // publish message with matching headers
        $message->set('application_headers', new AMQPTable($bindArguments));
        $this->channel->basicPublish($message, $this->exchange->name);

        $received1 = $this->channel->basicGet($queue1, true);
        $received2 = $this->channel->basicGet($queue2, true);

        // should appear in both queues
        $this->assertInstanceOf(AMQPMessage::class, $received1);
        $this->assertInstanceOf(AMQPMessage::class, $received2);

        // publish with not matching headers
        $message->set('application_headers', new AMQPTable(array('foo' => false)));
        $this->channel->basicPublish($message, $this->exchange->name);

        $received1 = $this->channel->basicGet($queue1, true);
        $received2 = $this->channel->basicGet($queue2, true);

        // should appear in non filtered queue only
        $this->assertInstanceOf(AMQPMessage::class, $received1);
        $this->assertNull($received2);
    }
}

<?php

namespace PhpAmqpLib\Tests\Functional\Channel;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * @group connection
 */
class HeadersExchangeTest extends ChannelTestCase
{
    protected function setUpCompat()
    {
        parent::setUpCompat();
        $this->exchange->name = 'amq.headers';
    }

    /**
     * @test
     * @small
     * @covers \PhpAmqpLib\Channel\AMQPChannel::queue_bind()
     * @covers \PhpAmqpLib\Exchange\AMQPExchangeType
     */
    public function consume_specific_headers()
    {
        list($queue1) = $this->channel->queue_declare();
        $this->channel->queue_bind($queue1, $this->exchange->name);

        $bindArguments = [
            'foo' => 'bar',
        ];
        list($queue2) = $this->channel->queue_declare();
        $this->channel->queue_bind($queue2, $this->exchange->name, '', false, new AMQPTable($bindArguments));

        // publish message without headers - should appear in 1st queue without filters
        $message = new AMQPMessage('test');
        $this->channel->basic_publish($message, $this->exchange->name);

        $received1 = $this->channel->basic_get($queue1, true);
        $received2 = $this->channel->basic_get($queue2, true);

        $this->assertInstanceOf(AMQPMessage::class, $received1);
        $this->assertNull($received2);

        // publish message with matching headers
        $message->set('application_headers', new AMQPTable($bindArguments));
        $this->channel->basic_publish($message, $this->exchange->name);

        $received1 = $this->channel->basic_get($queue1, true);
        $received2 = $this->channel->basic_get($queue2, true);

        // should appear in both queues
        $this->assertInstanceOf(AMQPMessage::class, $received1);
        $this->assertInstanceOf(AMQPMessage::class, $received2);

        // publish with not matching headers
        $message->set('application_headers', new AMQPTable(array('foo' => false)));
        $this->channel->basic_publish($message, $this->exchange->name);

        $received1 = $this->channel->basic_get($queue1, true);
        $received2 = $this->channel->basic_get($queue2, true);

        // should appear in non filtered queue only
        $this->assertInstanceOf(AMQPMessage::class, $received1);
        $this->assertNull($received2);
    }
}

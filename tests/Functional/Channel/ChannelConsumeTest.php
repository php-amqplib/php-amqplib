<?php

namespace PhpAmqpLib\Tests\Functional\Channel;

class ChannelConsumeTest extends ChannelTestCase
{
    /**
     * @test
     */
    public function basic_consume_same_tag_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        list($queue, ,) = $this->channel->queue_declare();
        $consumerTag = $this->channel->basic_consume($queue, '');
        $this->channel->basic_consume($queue, $consumerTag);
    }
}

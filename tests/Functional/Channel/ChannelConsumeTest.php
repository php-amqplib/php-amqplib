<?php

namespace PhpAmqpLib\Tests\Functional\Channel;

class ChannelConsumeTest extends ChannelTestCase
{
    /**
     * @test
     */
    public function basicConsumeSameTagThrosException()
    {
        $this->expectException(\InvalidArgumentException::class);
        list($queue, ,) = $this->channel->queueDeclare();
        $consumerTag = $this->channel->basicConsume($queue, '');
        $this->channel->basicConsume($queue, $consumerTag);
    }
}

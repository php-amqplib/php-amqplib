<?php

namespace PhpAmqpLib\Tests\Functional\Connection;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;
use PhpAmqpLib\Channel\AMQPChannel;

class AMQPStreamConnectionTest extends AbstractConnectionTest
{
    /** @test */
    public function it_should_fallback_to_second_node_on_first_node_failure()
    {
        $connection = AMQPStreamConnection::create_connection([
            [
                'host' => HOST,
                'port' => '5671', // / nothing here, node down...
                'vhost' => VHOST,
                'user' => USER,
                'password' => PASS,
            ],
            [
                'host' => HOST,
                'port' => PORT,
                'vhost' => VHOST,
                'user' => USER,
                'password' => PASS,
            ],
        ]);

        $channel = $connection->channel();
        $this->assertInstanceOf(AMQPChannel::class, $channel);
    }
}

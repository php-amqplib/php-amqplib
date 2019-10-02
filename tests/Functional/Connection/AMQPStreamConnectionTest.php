<?php

namespace PhpAmqpLib\Tests\Functional\Connection;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;

class AMQPStreamConnectionTest extends AbstractConnectionTest
{
    /** @test */
    public function it_should_fallback_to_second_node_on_first_node_failure()
    {
        $connection = AMQPStreamConnection::create_connection([
            [
                'host' => HOST,
                'port' => '5671',
                'vhost' => VHOST,
                'user' => USER,
                'password' => PASS,
            ],
            [
                'host' => HOST,
                'port' => '5672',
                'vhost' => VHOST,
                'user' => USER,
                'password' => PASS,
            ],
        ]);

        $channel = $connection->channel();
        $this->queue_bind($channel, $exchange_name = 'test_exchange', $queue_name);
        $message = new AMQPMessage('', ['content_type' => 'application/json']);
        $channel->basic_publish($message, $exchange_name, $queue_name);
    }
}

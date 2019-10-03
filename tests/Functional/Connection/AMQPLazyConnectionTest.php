<?php

namespace PhpAmqpLib\Tests\Functional\Connection;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;

class AMQPLazyConnectionTest extends AbstractConnectionTest
{
    /** @test */
    public function it_should_fallback_to_second_node_on_first_node_failure()
    {
        $connection = AMQPLazyConnection::create_connection([
            [
                'host' => HOST,
                'port' => '5671', // nothing here, node down...
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
        assert($connection instanceof AMQPLazyConnection);

        $channel = $connection->channel();
        $this->assertInstanceOf(AMQPChannel::class, $channel);
    }
}

<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;

class ChannelTest extends AbstractPublishConsumeTest
{
    protected function createConnection()
    {
        return new AMQPSocketConnection(HOST, PORT, USER, PASS, VHOST);
    }

    public function testDeclaringExchangeOnChannelWithClosedConnectionThrowsException()
    {
        $connection = $this->createConnection();
        $channel = $connection->channel();

        try {
            $channel->exchange_declare('tst.exchange2', 'topic', true);
        } catch (AMQPProtocolChannelException $e) {
            // Do Nothing
        }

        $this->setExpectedException('\PhpAmqpLib\Exception\AMQPRuntimeException', 'Channel connection is closed.');
        $channel->exchange_declare('tst.exchange2', 'topic', false, true, false);

        $channel->close();
        $connection->close();
    }
}

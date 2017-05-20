<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Exception\AMQPBasicCancelException;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Helper\Protocol\Wait091;
use PhpAmqpLib\Message\AMQPMessage;

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

    public function testPublishWithConfirm()
    {
        $connection = $this->createConnection();
        $connection->connectOnConstruct();
        $channel = $connection->channel();

        try {
            $channel->exchange_declare('tst.exchange3', 'topic', false);
        } catch (AMQPProtocolChannelException $e) {
            // Do Nothing
        }

        $deliveryTags = array();

        $channel->set_ack_handler(function (AMQPMessage $message) use (&$deliveryTags) {
            $deliveryTags[] = (int) $message->get('delivery_tag');
            return false;
        });

        $channel->confirm_select();

        $connection2 = $this->createConnection();
        $channel2 = $connection2->channel();

        $channel2->queue_declare('tst.queue3');
        $channel2->queue_bind('tst.queue3', 'tst.exchange3', '#');

        $channel->basic_publish(new AMQPMessage('foo'), 'tst.exchange3');
        $channel->basic_publish(new AMQPMessage('bar'), 'tst.exchange3');

        $publishedMessagesProperty = new \ReflectionProperty(get_class($this->channel), 'published_messages');
        $publishedMessagesProperty->setAccessible(true);

        $channel->wait_for_pending_acks_returns(1);

        $msg1 = $channel2->basic_get('tst.queue3');
        $this->assertInstanceOf('PhpAmqpLib\Message\AMQPMessage', $msg1);
        $msg2 = $channel2->basic_get('tst.queue3');
        $this->assertInstanceOf('PhpAmqpLib\Message\AMQPMessage', $msg2);

        $this->assertSame('foo', $msg1->getBody());
        $this->assertSame('bar', $msg2->getBody());

        $this->assertSame(array(1,2), $deliveryTags);

        $channel2->queue_delete('tst.queue3');
        $channel->exchange_delete('tst.exchange3');
    }
}

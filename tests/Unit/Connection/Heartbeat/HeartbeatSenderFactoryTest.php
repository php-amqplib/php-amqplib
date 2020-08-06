<?php
namespace PhpAmqpLib\Tests\Unit\Connection\Heartbeat;

use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Connection\Heartbeat\HeartbeatSenderFactory;
use PhpAmqpLib\Connection\Heartbeat\NullHeartbeatSender;
use PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender;
use PHPUnit\Framework\TestCase;

class HeartbeatSenderFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function create_pcntl_heartbeat_sender()
    {
        $connection = $this->createMock(AMQPSocketConnection::class);
        $sender = HeartbeatSenderFactory::getSender($connection, true);

        $this->assertInstanceOf(PCNTLHeartbeatSender::class, $sender);
    }

    /**
     * @test
     */
    public function create_null_heartbeat_sender()
    {
        $connection = $this->createMock(AMQPSocketConnection::class);
        $sender = HeartbeatSenderFactory::getSender($connection, false);
        $this->assertInstanceOf(NullHeartbeatSender::class, $sender);
    }
}

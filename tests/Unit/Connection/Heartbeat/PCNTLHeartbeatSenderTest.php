<?php
namespace PhpAmqpLib\Tests\Unit\Connection\Heartbeat;

use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender;
use PHPUnit\Framework\TestCase;

class PCNTLHeartbeatSenderTest extends TestCase
{
    /**
     * @test
     */
    public function heartbeat_interrupts_blocking_action()
    {
        $connection = $this->createMock(AMQPSocketConnection::class);
        $sender = new PCNTLHeartbeatSender($connection);

        $sender->setHeartbeat(4);

        $timeLeft = 4;
        $continuation = 0;
        while ($timeLeft > 0) {
            $timeLeft = sleep($timeLeft);
            $continuation++;
        }

        $sender->shutdown();

        self::assertEquals(2, $continuation);
    }
}
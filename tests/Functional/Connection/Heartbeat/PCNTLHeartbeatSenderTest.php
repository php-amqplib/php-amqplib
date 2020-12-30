<?php

namespace PhpAmqpLib\Tests\Functional\Connection\Heartbeat;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;

/**
 * @group connection
 * @group signals
 * @requires extension pcntl
 * @requires PHP 7.1
 */
class PCNTLHeartbeatSenderTest extends AbstractConnectionTest
{
    /** @var AbstractConnection */
    protected $connection;

    /** @var PCNTLHeartbeatSender */
    protected $sender;

    /** @var int */
    protected $heartbeatTimeout = 4;

    protected function setUp()
    {
        $this->connection = $this->conectionCreate(
            'stream',
            HOST,
            PORT,
            ['timeout' => 3, 'heartbeat' => $this->heartbeatTimeout]
        );
        try {
            $this->sender = new PCNTLHeartbeatSender($this->connection);
        } catch (\Exception $exception) {
            $this->markTestSkipped($exception->getMessage());
        }
    }

    public function tearDown()
    {
        if ($this->sender) {
            $this->sender->unregister();
        }
        if ($this->connection) {
            $this->connection->close();
        }
        $this->sender = null;
        $this->connection = null;
    }

    /**
     * @test
     */
    public function registerShouldFailWithClosedConnection()
    {
        $this->expectException(AMQPRuntimeException::class);
        $this->expectExceptionMessage('Unable to register heartbeat sender, connection is not active');

        $this->connection->close();
        $this->sender->register();
    }

    /**
     * @test
     */
    public function registerShouldFailAfterUnregister()
    {
        $this->expectException(AMQPRuntimeException::class);
        $this->expectExceptionMessage('Unable to re-register heartbeat sender');

        $this->sender->unregister();
        $this->sender->register();
    }

    /**
     * @test
     */
    public function unregisterShouldReturnDefaultSignalHandler()
    {
        $this->sender->register();
        $this->sender->unregister();

        self::assertEquals(SIG_IGN, pcntl_signal_get_handler(SIGALRM));
    }

    /**
     * @test
     */
    public function heartbeatShouldInterruptNonBlockingAction()
    {
        $this->sender->register();

        $timeLeft = $this->heartbeatTimeout;
        $continuation = 0;
        while ($timeLeft > 0) {
            $timeLeft = sleep($timeLeft);
            $continuation++;
        }

        self::assertEquals(2, $continuation);
    }
}

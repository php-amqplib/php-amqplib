<?php
namespace PhpAmqpLib\Tests\Unit\Connection\Heartbeat;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PHPUnit\Framework\TestCase;

class PCNTLHeartbeatSenderTest extends TestCase
{
    /**
     * @var AbstractConnection
     */
    protected $connection;

    /**
     * @var int
     */
    protected $heartbeatTimeout = 4;

    protected function setUp()
    {
        if (!function_exists('pcntl_async_signals')) {
            $this->markTestSkipped('pcntl_async_signals is required');
        }

        $this->connection = AMQPStreamConnection::create_connection([
            ['host' => HOST, 'port' => PORT, 'user' => USER, 'password' => PASS, 'vhost' => VHOST]
        ], [
            'heartbeat' => $this->heartbeatTimeout,
        ]);
    }

    public function tearDown()
    {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * @test
     */
    public function register_should_fail_with_closed_connection()
    {
        $this->expectException(AMQPRuntimeException::class);
        $this->expectExceptionMessage('Unable to register heartbeat sender, connection is not active');

        $this->connection->close();
        $sender = new PCNTLHeartbeatSender($this->connection);
        $sender->register();
    }

    /**
     * @test
     */
    public function register_should_fail_after_unregister()
    {
        $this->expectException(AMQPRuntimeException::class);
        $this->expectExceptionMessage('Unable to re-register heartbeat sender');

        $sender = new PCNTLHeartbeatSender($this->connection);
        $sender->unregister();
        $sender->register();
    }

    /**
     * @test
     */
    public function unregister_should_return_default_signal_handler()
    {
        $sender = new PCNTLHeartbeatSender($this->connection);
        $sender->register();
        $sender->unregister();

        $this->assertEquals(SIG_IGN, pcntl_signal_get_handler(SIGALRM));
    }

    /**
     * @test
     */
    public function heartbeat_should_interrupt_non_blocking_action()
    {
        $sender = new PCNTLHeartbeatSender($this->connection);
        $sender->register();

        $timeLeft = $this->heartbeatTimeout;
        $continuation = 0;
        while ($timeLeft > 0) {
            $timeLeft = sleep($timeLeft);
            $continuation++;
        }

        self::assertEquals(2, $continuation);
    }
}

<?php

namespace PhpAmqpLib\Tests\Functional\Channel;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\TestCase;

class ChannelWaitTest extends TestCase
{
    /**
     * @test
     * @small
     * @dataProvider provide_channels
     * @param callable $factory
     */
    public function should_wait_until_signal_by_default($factory)
    {
        $this->deferSignal(0.5);
        /** @var AMQPChannel $channel */
        $channel = $factory();
        try {
            $channel->wait();
        } catch (\Exception $exception) {
            $this->fail($exception->getMessage());
        }

        $this->closeChannel($channel);
    }

    /**
     * @test
     * @small
     * @dataProvider provide_channels
     * @param callable $factory
     * @expectedException \PhpAmqpLib\Exception\AMQPTimeoutException
     */
    public function should_throw_timeout_exception($factory)
    {
        $channel = $factory();
        $channel->wait(null, false, 0.01);
        $this->closeChannel($channel);
    }

    /**
     * @test
     * @small
     * @dataProvider provide_channels
     * @param callable $factory
     */
    public function should_return_instantly_non_blocking($factory)
    {
        $channel = $factory();
        $start = microtime(true);
        $channel->wait(null, true);
        $took = microtime(true) - $start;

        $this->assertLessThan(0.1, $took);

        $this->closeChannel($channel);
    }

    public function provide_channels()
    {
        if (!defined('HOST')) {
            $this->markTestSkipped('Unkown RabbitMQ host');
        }

        return [
            [$this->channelFactory(true, 0.1, 0)],
            [$this->channelFactory(false, 0.1, 0)],
            [$this->channelFactory(true, 3, 1)],
            [$this->channelFactory(false, 3, 1)],
        ];
    }

    protected function channelFactory($stream = true, $connectionTimeout = 1, $heartBeat = 0)
    {
        $factory = function () use ($stream, $connectionTimeout, $heartBeat) {
            try {
                if ($stream) {
                    $connection = new AMQPStreamConnection(
                        HOST, PORT, USER, PASS, VHOST,
                        false, 'AMQPLAIN', null, 'en_us',
                        $connectionTimeout, $connectionTimeout, null, false, $heartBeat
                    );
                } else {
                    $connection = new AMQPSocketConnection(
                        HOST, PORT, USER, PASS, VHOST,
                        false, 'AMQPLAIN', null, 'en_US',
                        $connectionTimeout,
                        false,
                        $connectionTimeout,
                        $heartBeat
                    );
                }
            } catch (\ErrorException $exception) {
                $this->markTestSkipped('Cannot connect to RabbitMQ: ' . $exception->getMessage());
            }

            $channel = $connection->channel();
            $channel->queue_declare($queue = 'basic_get_queue', false, true, false, false);
            $channel->exchange_declare($exchange = 'basic_get_test', 'fanout', false, true, false);
            $channel->queue_bind($queue, $exchange);

            return $channel;
        };

        return $factory;
    }

    protected function deferSignal($delay = 1)
    {
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl extension is not available');
        }
        pcntl_signal(SIGTERM, function () {
        });
        $pid = getmypid();
        exec('php -r "usleep(' . $delay * 1e6 . ');posix_kill(' . $pid . ', SIGTERM);" > /dev/null 2>/dev/null &');
    }

    protected function closeChannel(AMQPChannel $channel)
    {
        $connection = $channel->getConnection();
        $channel->close();
        $connection->close();
    }
}

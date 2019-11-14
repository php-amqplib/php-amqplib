<?php

namespace PhpAmqpLib\Tests\Functional\Bug;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\TestCase;

/**
 * @group connection
 * @group signals
 */
class Bug458Test extends TestCase
{
    private $channel;

    protected function setUp()
    {
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl extension is not available');
        }

        $connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);

        $this->channel = $connection->channel();
        $this->addSignalHandlers();
    }

    protected function tearDown()
    {
        if ($this->channel && $this->channel->is_open()) {
            $this->channel->close();
        }
        $this->channel = null;
    }

    /**
     * This test will be skipped in Windows, because pcntl extension is not available there
     *
     * @test
     *
     * @expectedException \PhpAmqpLib\Exception\AMQPTimeoutException
     */
    public function stream_select_interruption()
    {
        $pid = getmypid();
        exec('php -r "sleep(1);posix_kill(' . $pid . ', SIGTERM);" > /dev/null 2>/dev/null &');
        $this->channel->wait(null, false, 2);
    }

    private function addSignalHandlers()
    {
        pcntl_signal(SIGTERM, function () {
            // do nothing
        });
    }
}

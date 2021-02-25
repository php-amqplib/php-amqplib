<?php

namespace PhpAmqpLib\Tests\Functional\Bug;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Tests\TestCaseCompat;

/**
 * @group connection
 * @group signals
 */
class Bug458Test extends TestCaseCompat
{
    private $channel;

    protected function setUpCompat()
    {
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl extension is not available');
        }

        $connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);

        $this->channel = $connection->channel();
        $this->addSignalHandlers();
    }

    protected function tearDownCompat()
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
     */
    public function stream_select_interruption()
    {
        $this->expectException(\PhpAmqpLib\Exception\AMQPTimeoutException::class);

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

<?php

namespace PhpAmqpLib\Tests\Functional\Bug;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\TestCase;

class Bug458Test extends TestCase
{
    private $channel;

    public function setUp()
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
        $this->channel->close();
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

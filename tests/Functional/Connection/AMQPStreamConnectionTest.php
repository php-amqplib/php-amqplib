<?php

namespace PhpAmqpLib\Tests\Functional\Connection;

use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;
use PhpAmqpLib\Tests\Functional\Channel\ChannelWaitTest;

class AMQPStreamConnectionTest extends AbstractConnectionTest
{
    /**
     * @test
     * @group connection
     * @covers \PhpAmqpLib\Wire\IO\StreamIO::select()
     */
    public function connection_select_blocking_wo_timeout(): void
    {
        $connection = $this->connection_create('stream');

        $start = microtime(true);
        ChannelWaitTest::deferSignal(1);
        $result = $connection->select(null);
        $took = microtime(true) - $start;

        self::assertEquals(0, $result);
        self::assertGreaterThanOrEqual(.9, $took);
    }
}

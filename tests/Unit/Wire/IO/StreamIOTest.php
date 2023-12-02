<?php

namespace PhpAmqpLib\Tests\Unit\Wire\IO;

use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Wire\IO\StreamIO;
use PHPUnit\Framework\TestCase;

/**
 * @group connection
 */
class StreamIOTest extends TestCase
{
    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage read_write_timeout must be at least 2x the heartbeat
     * TODO FUTURE re-enable this test
    public function read_write_timeout_must_be_at_least_2x_the_heartbeat()
    {
        new StreamIO(
            'localhost',
            '5512',
            1,
            1,
            null,
            false,
            1
        );
    }
     */

    /**
     * @test
     * @group linux
     * @requires OS Linux
     */
    public function select_must_throw_io_exception()
    {
        $this->expectException(AMQPConnectionClosedException::class);
        $property = new \ReflectionProperty(StreamIO::class, 'sock');
        $property->setAccessible(true);

        $resource = fopen('php://temp', 'r');
        fclose($resource);

        $stream = new StreamIO('0.0.0.0', PORT, 0.1, 0.1, null, false, 0);
        $property->setValue($stream, $resource);

        $stream->select(0, 0);
    }

    /**
     * @test
     */
    public function connect_ipv6()
    {
        $streamIO = new StreamIO(HOST6, PORT, 0.1, 0.1, null, false, 0);
        $streamIO->connect();
        $ready = $streamIO->select(0, 0);
        $this->assertEquals(0, $ready);
    }
}

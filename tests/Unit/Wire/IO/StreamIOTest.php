<?php

namespace PhpAmqpLib\Tests\Unit\Wire\IO;

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
     * @expectedException \PhpAmqpLib\Exception\AMQPIOWaitException
     * @requires OS Linux
     */
    public function selectMustThrowIoException()
    {
        $property = new \ReflectionProperty(StreamIO::class, 'sock');
        $property->setAccessible(true);

        $resource = fopen('php://temp', 'r');
        fclose($resource);

        $stream = new StreamIO('0.0.0.0', PORT, 0.1, 0.1, null, false, 0);
        $property->setValue($stream, $resource);

        $stream->select(0, 0);
    }
}

<?php

namespace PhpAmqpLib\Tests\Unit\Wire\IO;

use PhpAmqpLib\Wire\IO\StreamIO;
use PHPUnit\Framework\TestCase;

/**
 * Tests StreamIO.
 */
class StreamIOTest extends TestCase
{

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage read_write_timeout must be at least 2x the heartbeat
     */
    public function testReadWriteTimeoutMustBeAtLeastTwiceHeartbeat()
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
}

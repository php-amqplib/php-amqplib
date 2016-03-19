<?php
/**
 * Tests StreamIO.
 */
namespace PhpAmqpLib\Tests\Unit\Wire\IO;

use \PhpAmqpLib\Wire\IO\StreamIO;

class StreamIOTest extends \PHPUnit_Framework_TestCase
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

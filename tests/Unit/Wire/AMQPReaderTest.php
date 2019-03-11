<?php

namespace PhpAmqpLib\Tests\Unit\Wire;

use PhpAmqpLib\Wire;
use PhpAmqpLib\Wire\AMQPReader;
use PHPUnit\Framework\TestCase;

class AMQPReaderTest extends TestCase
{

    public function setUp(): void
    {
        $this->setProtoVersion(Wire\Constants091::VERSION);
    }

    protected function setProtoVersion($proto)
    {
        $r = new \ReflectionProperty('\\PhpAmqpLib\\Wire\\AMQPAbstractCollection', '_protocol');
        $r->setAccessible(true);
        $r->setValue(null, $proto);
    }

    public function tearDown(): void
    {
    }

    public function testReadBytes()
    {
        $expected = [
            'snowman' => ['x', "\x26\x03"]
        ];
        $data = hex2bin('0000000f07736e6f776d616e78000000022603');
        $reader = new AMQPReader($data);
        $parsed = $reader->read_table();
        $this->assertEquals($expected, $parsed);
    }
}

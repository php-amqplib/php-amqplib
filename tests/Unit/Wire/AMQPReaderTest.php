<?php

namespace PhpAmqpLib\Tests\Unit\Wire;

use PhpAmqpLib\Wire;
use PhpAmqpLib\Wire\AMQPReader;
use PHPUnit\Framework\TestCase;

class AMQPReaderTest extends TestCase
{

    public function setUp()
    {
        $this->setProtoVersion(Wire\Constants091::VERSION);
    }

    protected function setProtoVersion($proto)
    {
        $r = new \ReflectionProperty('\\PhpAmqpLib\\Wire\\AMQPAbstractCollection', '_protocol');
        $r->setAccessible(true);
        $r->setValue(null, $proto);
    }

    public function tearDown()
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

    public function test32bitSignedIntegerOverflow()
    {
        $data = hex2bin('0000000080000000');
        $reader = new AMQPReader($data);
        $parsed = $reader->read_signed_longlong();
        if (PHP_INT_SIZE === 8) {
            $this->assertInternalType('integer', $parsed);
            $this->assertEquals(0x80000000, $parsed);
        } else {
            $this->assertInternalType('string', $parsed);
            $this->assertEquals('2147483648', $parsed);
        }
    }

    public function test64bitUnsignedIntegerOverflow()
    {
        $data = hex2bin(str_repeat('f', 16));
        $reader = new AMQPReader($data);
        $parsed = $reader->read_longlong();
        $this->assertInternalType('string', $parsed);
        $this->assertEquals('18446744073709551615', $parsed);
    }
}

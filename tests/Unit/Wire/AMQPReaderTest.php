<?php

namespace PhpAmqpLib\Tests\Unit\Wire;

use PhpAmqpLib\Wire;
use PhpAmqpLib\Wire\AMQPBufferReader;
use PhpAmqpLib\Tests\TestCaseCompat;
use PhpAmqpLib\Wire\AMQPAbstractCollection;

class AMQPReaderTest extends TestCaseCompat
{
    protected function setUpCompat()
    {
        $this->setProtoVersion(Wire\Constants091::VERSION);
    }

    protected function setProtoVersion($proto)
    {
        $r = new \ReflectionProperty(AMQPAbstractCollection::class, 'protocol');
        $r->setAccessible(true);
        $r->setValue(null, $proto);
    }

    public function testReadBytes()
    {
        $expected = [
            'snowman' => ['x', "\x26\x03"]
        ];
        $data = hex2bin('0000000f07736e6f776d616e78000000022603');
        $reader = new AMQPBufferReader($data);
        $parsed = $reader->read_table();
        $this->assertEquals($expected, $parsed);
    }

    public function test32bitSignedIntegerOverflow()
    {
        $data = hex2bin('0000000080000000');
        $reader = new AMQPBufferReader($data);
        $parsed = $reader->read_signed_longlong();
        if (PHP_INT_SIZE === 8) {
            $this->assertIsInt($parsed);
            $this->assertEquals(0x80000000, $parsed);
        } else {
            $this->assertIsString($parsed);
            $this->assertEquals('2147483648', $parsed);
        }
    }

    public function test64bitUnsignedIntegerOverflow()
    {
        $data = hex2bin(str_repeat('f', 16));
        $reader = new AMQPBufferReader($data);
        $parsed = $reader->read_longlong();
        $this->assertIsString($parsed);
        $this->assertEquals('18446744073709551615', $parsed);
    }

    public function testDecodeFloatingPointValues()
    {
        $data = hex2bin('3a83126f');
        $reader = new AMQPBufferReader($data);
        $parsed = $reader->read_value(AMQPAbstractCollection::T_FLOAT);
        self::assertIsFloat($parsed);
        self::assertEquals(0.0010000000474974513, $parsed);

        $data = hex2bin('3feff7ced916872b');
        $reader = new AMQPBufferReader($data);
        $parsed = $reader->read_value(AMQPAbstractCollection::T_DOUBLE);
        self::assertIsFloat($parsed);
        self::assertEquals(0.999, $parsed);
    }
}

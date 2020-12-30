<?php

namespace PhpAmqpLib\Tests\Unit;

use PhpAmqpLib\Wire\AMQPArray;
use PhpAmqpLib\Wire\AMQPReader;
use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Wire\AMQPWriter;
use PHPUnit\Framework\TestCase;

class WireTest extends TestCase
{
    /**
     * @dataProvider bitWrData
     * @test
     */
    public function bitWr($value)
    {
        $this->wr($value, 'writeBit', 'readBit');
    }

    /**
     * @dataProvider octetWrData
     * @test
     */
    public function octetWr($value)
    {
        $this->wr($value, 'writeOctet', 'readOctet');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function octetWrOutOfRangeLower()
    {
        $this->wr(-1, 'writeOctet', 'readOctet');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function octetWrOutOfRangeUpper()
    {
        $this->wr(256, 'writeOctet', 'readOctet');
    }

    /**
     * @dataProvider signedOctetWrData
     * @test
     */
    public function signedOctetWr($value)
    {
        $this->wr($value, 'writeSignedOctet', 'readSignedOctet');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signedOctetWrOutOfRangeLower()
    {
        $this->wr(-129, 'writeSignedOctet', 'readSignedOctet');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signedOctetWrOutOfRangeUpper()
    {
        $this->wr(128, 'writeSignedOctet', 'readSignedOctet');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     * @expectedExceptionMessage Short out of range: 65536
     */
    public function shortWr()
    {
        $this->wr(65536, 'writeShort', 'readShort');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function shortWrOutOfRangeLower()
    {
        $this->wr(-1, 'writeShort', 'readShort');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function shortWrOutOfRangeUpper()
    {
        $this->wr(65536, 'writeShort', 'readShort');
    }

    /**
     * @dataProvider signedShortWrData
     * @test
     */
    public function signedShortWr($value)
    {
        $this->wr($value, 'writeSignedShort', 'readSignedShort');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signedShortWrOutOfRangeLower()
    {
        $this->wr(-32769, 'writeSignedShort', 'readSignedShort');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signedShortWrOutOfRangeUpper()
    {
        $this->wr(32768, 'writeSignedShort', 'readSignedShort');
    }

    /**
     * @dataProvider longWrData
     * @test
     */
    public function longWr($value)
    {
        $this->wr($value, 'writeLong', 'readLong');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function longWrOutOfRangeLower()
    {
        $this->wr(-1, 'writeLong', 'readLong');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function longWrOutOfRangeUpper()
    {
        $this->wr('4294967296', 'writeLong', 'readLong');
    }

    /**
     * @dataProvider signedLongWrData
     * @test
     */
    public function signedLongWr($value)
    {
        $this->wr($value, 'writeSignedLong', 'readSignedLong', true);
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signedLongWrOutOfRangeLower()
    {
        $this->wr('-2147483649', 'writeSignedLong', 'readSignedLong', true);
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signedLongWrOutOfRangeUpper()
    {
        $this->wr('2147483648', 'writeSignedLong', 'readSignedLong', true);
    }

    /**
     * @dataProvider longlongWrData
     * @test
     */
    public function longlongWr($value)
    {
        $this->wr($value, 'writeLonglong', 'readLonglong');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function longlongWrOutOfRangeLower()
    {
        $this->wr('-1', 'writeLonglong', 'readLonglong');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function longlongWrOutOfRangeUpper()
    {
        $this->wr('18446744073709551616', 'writeLonglong', 'readLonglong');
    }

    /**
     * @dataProvider signedLonglongWrData
     * @test
     */
    public function signedLonglongWr($value)
    {
        $this->wr($value, 'writeSignedLonglong', 'readSignedLonglong');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signedLonglongWrOutOfRangeLower()
    {
        $this->wr('-9223372036854775809', 'writeSignedLonglong', 'readSignedLonglong');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signedLonglongWrOutOfRangeUpper()
    {
        $this->wr('9223372036854775808', 'writeSignedLonglong', 'readSignedLonglong');
    }

    /**
     * @dataProvider shortstrWrData
     * @test
     */
    public function shortstrWr($value)
    {
        $this->wr($value, 'writeShortstr', 'readShortstr');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function shortstrWrOutOfRangeAscii()
    {
        $this->wr(str_repeat('z', 256), 'writeShortstr', 'readShortstr');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function shortstrWrOutOfRangeUtfTwoByte()
    {
        $this->wr(str_repeat("\xd0\xaf", 128), 'writeShortstr', 'readShortstr');
    }

    /**
     * @dataProvider longstrWrData
     * @test
     */
    public function longstrWr($value)
    {
        $this->wr($value, 'writeLongstr', 'readLongstr');
    }

    /**
     * @test
     */
    public function arrayWrNative()
    {
        $d = [
            1,
            -2147483648,
            2147483647,
            true,
            false,
            [['foo', ['bar']]],
            [],
        ];

        $w = new AMQPWriter();
        $w->writeArray($d);

        $r = new AMQPReader($w->getvalue());
        $rd = $r->readArray();

        $this->assertEquals($d, $rd);
    }

    /**
     * @test
     */
    public function arrayWrCollection()
    {
        $w = new AMQPWriter();
        $w->writeArray(
            new AMQPArray(
                [
                    12345,
                    -12345,
                    3000000000,
                    -3000000000,
                    9.2233720368548,
                    (float)9223372036854800000,
                    true,
                    false,
                    [1, 2, 3, 'foo', ['bar' => 'baz'], ['boo', false, 5], true],
                    [],
                    [
                        'foo' => 'bar',
                        'baz' => 'boo',
                        'bool' => true,
                        'tbl' => ['bar' => 'baz'],
                        'arr' => ['boo', false, 5]
                    ],
                    [1 => 5, 3 => 'foo', 786 => 674],
                    [1, [2, [3, [4]]]],
                    ['i' => 1, 'n' => ['i' => 2, 'n' => ['i' => 3, 'n' => ['i' => 4]]]]
                ]
            )
        );

        $r = new AMQPReader($w->getvalue());

        //type casting - thanks to ancient phpunit on travis
        $this->assertEquals(
            [
                12345,
                -12345,
                (string)3000000000,
                (string)-3000000000,
                (string)(float)9.2233720368548,
                (string)(float)9223372036854800000,
                true,
                false,
                [1, 2, 3, 'foo', ['bar' => 'baz'], ['boo', false, 5], true],
                [],
                [
                    'foo' => 'bar',
                    'baz' => 'boo',
                    'bool' => true,
                    'tbl' => ['bar' => 'baz'],
                    'arr' => ['boo', false, 5]
                ],
                [1 => 5, 3 => 'foo', 786 => 674],
                [1, [2, [3, [4]]]],
                ['i' => 1, 'n' => ['i' => 2, 'n' => ['i' => 3, 'n' => ['i' => 4]]]]
            ],
            $r->readArray(true)->getNativeData()
        );
    }

    /**
     * @test
     */
    public function tableWrNative()
    {
        $d = [
            'a' => ['I', 1],
            'b' => ['I', -2147483648],
            'c' => ['I', 2147483647],
            'd' => ['l', '-2147483649'],
            'e' => ['l', '2147483648'],
            'f' => ['t', true],
            'g' => ['t', false],
            'h' => ['F', ['foo' => ['S', 'baz']]],
            'i' => ['A', [['foo', ['bar']]]],
            'j' => ['A', []],
        ];

        $w = new AMQPWriter();
        $w->writeTable($d);

        $r = new AMQPReader($w->getvalue());
        $rd = $r->readTable();

        $this->assertEquals($d, $rd);
    }

    /**
     * @test
     */
    public function table_wr_collection()
    {
        $w = new AMQPWriter();
        $w->writeTable(
            new AMQPTable(
                [
                    'long' => 12345,
                    'long_neg' => -12345,
                    'longlong' => 3000000000,
                    'longlong_neg' => -3000000000,
                    'float_low' => 9.2233720368548,
                    'float_high' => (float)9223372036854800000,
                    'bool_true' => true,
                    'bool_false' => false,
                    'array' => [1, 2, 3, 'foo', ['bar' => 'baz'], ['boo', false, 5], true],
                    'array_empty' => [],
                    'table' => [
                        'foo' => 'bar',
                        'baz' => 'boo',
                        'bool' => true,
                        'tbl' => ['bar' => 'baz'],
                        'arr' => ['boo', false, 5]
                    ],
                    'table_num' => [1 => 5, 3 => 'foo', 786 => 674],
                    'array_nested' => [1, [2, [3, [4]]]],
                    'table_nested' => [
                        'i' => 1,
                        'n' => ['i' => 2, 'n' => ['i' => 3, 'n' => ['i' => 4]]],
                    ],
                ]
            )
        );

        $r = new AMQPReader($w->getvalue());

        //type casting - thanks to ancient phpunit on travis
        $this->assertEquals(
            [
                'long' => 12345,
                'long_neg' => -12345,
                'longlong' => (string)3000000000,
                'longlong_neg' => (string)-3000000000,
                'float_low' => (string)(float)9.2233720368548,
                'float_high' => (string)(float)9223372036854800000,
                'bool_true' => true,
                'bool_false' => false,
                'array' => [1, 2, 3, 'foo', ['bar' => 'baz'], ['boo', false, 5], true],
                'array_empty' => [],
                'table' => [
                    'foo' => 'bar',
                    'baz' => 'boo',
                    'bool' => true,
                    'tbl' => ['bar' => 'baz'],
                    'arr' => ['boo', false, 5]
                ],
                'table_num' => [1 => 5, 3 => 'foo', 786 => 674],
                'array_nested' => [1, [2, [3, [4]]]],
                'table_nested' => [
                    'i' => 1,
                    'n' => ['i' => 2, 'n' => ['i' => 3, 'n' => ['i' => 4]]]
                ]
            ],
            $r->readTable(true)->getNativeData()
        );
    }

    public function bitWrData()
    {
        return [
            [true],
            [false],
        ];
    }

    public function octetWrData()
    {
        $data = [];
        for ($i = 0; $i <= 255; $i++) {
            $data[] = [$i];
        }

        return $data;
    }

    public function signedOctetWrData()
    {
        $data = [];
        for ($i = -128; $i <= 127; $i++) {
            $data[] = [$i];
        }

        return $data;
    }

    public function signedShortWrData()
    {
        return [
            [-32768],
            [-32767],
            [32766],
            [32767],
        ];
    }

    public function longWrData()
    {
        $max = PHP_INT_SIZE === 8 ? 4294967295 : PHP_INT_MAX;

        return [
            [0],
            [1],
            [2],
            [2147483646],
            [2147483647],
            [2147483648],
            [$max - 1],
            [$max],
            ['0'],
            ['1'],
            ['2'],
            ['2147483646'],
            ['2147483647'],
            ['2147483648'],
            ['4294967293'],
            ['4294967294'],
            ['4294967295'],
        ];
    }

    public function signedLongWrData()
    {
        return [
            [-2147483648],
            [-2147483647],
            [-2],
            [-1],
            [0],
            [1],
            [2],
            [2147483646],
            [2147483647],
            ['-2147483648'],
            ['-2147483647'],
            ['-2147483646'],
            ['-2'],
            ['-1'],
            ['0'],
            ['1'],
            ['2'],
            ['2147483645'],
            ['2147483646'],
            ['2147483647'],
        ];
    }

    public function longlongWrData()
    {
        return [
            [0],
            [1],
            [2],
            [PHP_INT_MAX - 1],
            [PHP_INT_MAX],
            ['0'],
            ['1'],
            ['2'],
            ['2147483646'],
            ['2147483647'],
            ['2147483648'],
            ['4294967294'],
            ['4294967295'],
            ['4294967296'],
            ['9223372036854775806'],
            ['9223372036854775807'],
            ['9223372036854775808'],
            ['18446744073709551613'],
            ['18446744073709551614'],
            ['18446744073709551615'],
        ];
    }

    public function signedLonglongWrData()
    {
        $min = defined('PHP_INT_MIN') ? PHP_INT_MIN : ~PHP_INT_MAX;
        return [
            [$min],
            [$min + 1],
            ['-9223372036854775808'],
            ['-9223372036854775807'],
            ['-9223372036854775806'],
            ['-4294967297'],
            ['-4294967296'],
            ['-4294967295'],
            ['-2147483649'],
            ['-2147483648'],
            ['-2147483647'],
            ['-2'],
            ['-1'],
            [-1],
            ['0'],
            [0],
            ['1'],
            [1],
            ['2'],
            ['2147483646'],
            [2147483646],
            ['2147483647'],
            [2147483647], // 32-bit PHP_INT_MAX
            ['2147483648'],
            [2147483648], // float on 32-bit systems
            ['4294967294'],
            ['4294967295'],
            ['4294967296'],
            ['9223372036854775805'],
            ['9223372036854775806'],
            ['9223372036854775807'],
            [PHP_INT_MAX - 1],
            [PHP_INT_MAX],
        ];
    }

    public function shortstrWrData()
    {
        return [
            ['a'],
            ['üıß∑œ´®†¥¨πøˆ¨¥†®'],
        ];
    }

    public function longstrWrData()
    {
        return [
            ['a'],
            ['üıß∑œ´®†¥¨πøˆ¨¥†®'],
            [
                'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz' .
                'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz' .
                'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz'
            ]
        ];
    }

    protected function wr($value, $write_method, $read_method, $reflection = false)
    {
        $writer = new AMQPWriter();
        if ($reflection) {
            $m = new \ReflectionMethod($writer, $write_method);
            $m->setAccessible(true);
            $m->invoke($writer, $value);
        } else {
            $writer->{$write_method}($value);
        }

        $reader = new AMQPReader($writer->getvalue());
        if ($reflection) {
            $m = new \ReflectionMethod($reader, $read_method);
            $m->setAccessible(true);
            $readValue = $m->invoke($reader);
        } else {
            $readValue = $reader->{$read_method}();
        }

        $this->assertEquals(
            $value,
            $readValue,
            'Written: ' . bin2hex($writer->getvalue()) . ', read: ' . bin2hex($readValue)
        );
    }
}

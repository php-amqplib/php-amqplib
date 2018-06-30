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
    public function bit_wr($value)
    {
        $this->wr($value, 'write_bit', 'read_bit');
    }

    /**
     * @dataProvider octetWrData
     * @test
     */
    public function octet_wr($value)
    {
        $this->wr($value, 'write_octet', 'read_octet');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function octet_wr_out_of_range_lower()
    {
        $this->wr(-1, 'write_octet', 'read_octet');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function octet_wr_out_of_range_upper()
    {
        $this->wr(256, 'write_octet', 'read_octet');
    }

    /**
     * @dataProvider signedOctetWrData
     * @test
     */
    public function signed_octet_wr($value)
    {
        $this->wr($value, 'write_signed_octet', 'read_signed_octet');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signed_octet_wr_out_of_range_lower()
    {
        $this->wr(-129, 'write_signed_octet', 'read_signed_octet');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signed_octet_wr_out_of_range_upper()
    {
        $this->wr(128, 'write_signed_octet', 'read_signed_octet');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     * @expectedExceptionMessage Short out of range: 65536
     */
    public function short_wr()
    {
        $this->wr(65536, 'write_short', 'read_short');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function short_wr_out_of_range_lower()
    {
        $this->wr(-1, 'write_short', 'read_short');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function short_wr_out_of_range_upper()
    {
        $this->wr(65536, 'write_short', 'read_short');
    }

    /**
     * @dataProvider signedShortWrData
     * @test
     */
    public function signed_short_wr($value)
    {
        $this->wr($value, 'write_signed_short', 'read_signed_short');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signed_short_wr_out_of_range_lower()
    {
        $this->wr(-32769, 'write_signed_short', 'read_signed_short');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signed_short_wr_out_of_range_upper()
    {
        $this->wr(32768, 'write_signed_short', 'read_signed_short');
    }

    /**
     * @dataProvider longWrData
     * @test
     */
    public function long_wr($value)
    {
        $this->wr($value, 'write_long', 'read_long');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function long_wr_out_of_range_lower()
    {
        $this->wr(-1, 'write_long', 'read_long');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function long_wr_out_of_range_upper()
    {
        $this->wr('4294967296', 'write_long', 'read_long');
    }

    /**
     * @dataProvider signedLongWrData
     * @test
     */
    public function signed_long_wr($value)
    {
        $this->wr($value, 'write_signed_long', 'read_signed_long', true);
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signed_long_wr_out_of_range_lower()
    {
        $this->wr('-2147483649', 'write_signed_long', 'read_signed_long', true);
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signed_long_wr_out_of_range_upper()
    {
        $this->wr('2147483648', 'write_signed_long', 'read_signed_long', true);
    }

    /**
     * @dataProvider longlongWrData
     * @test
     */
    public function longlong_wr($value)
    {
        $this->wr($value, 'write_longlong', 'read_longlong');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function longlong_wr_out_of_range_lower()
    {
        $this->wr('-1', 'write_longlong', 'read_longlong');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function longlong_wr_out_of_range_upper()
    {
        $this->wr('18446744073709551616', 'write_longlong', 'read_longlong');
    }

    /**
     * @dataProvider signedLonglongWrData
     * @test
     */
    public function signed_longlong_wr($value)
    {
        $this->wr($value, 'write_signed_longlong', 'read_signed_longlong');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signed_longlong_wr_out_of_range_lower()
    {
        $this->wr('-9223372036854775809', 'write_signed_longlong', 'read_signed_longlong');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function signed_longlong_wr_out_of_range_upper()
    {
        $this->wr('9223372036854775808', 'write_signed_longlong', 'read_signed_longlong');
    }

    /**
     * @dataProvider shortstrWrData
     * @test
     */
    public function shortstr_wr($value)
    {
        $this->wr($value, 'write_shortstr', 'read_shortstr');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function shortstr_wr_out_of_range_ASCII()
    {
        $this->wr(str_repeat('z', 256), 'write_shortstr', 'read_shortstr');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function shortstr_wr_out_of_range_utf_two_byte()
    {
        $this->wr(str_repeat("\xd0\xaf", 128), 'write_shortstr', 'read_shortstr');
    }

    /**
     * @dataProvider longstrWrData
     * @test
     */
    public function longstr_wr($value)
    {
        $this->wr($value, 'write_longstr', 'read_longstr');
    }

    /**
     * @test
     */
    public function array_wr_native()
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
        $w->write_array($d);

        $r = new AMQPReader($w->getvalue());
        $rd = $r->read_array();

        $this->assertEquals($d, $rd);
    }

    /**
     * @test
     */
    public function array_wr_collection()
    {
        $w = new AMQPWriter();
        $w->write_array(
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
            $r->read_array(true)->getNativeData()
        );
    }

    /**
     * @test
     */
    public function table_wr_native()
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
        $w->write_table($d);

        $r = new AMQPReader($w->getvalue());
        $rd = $r->read_table();

        $this->assertEquals($d, $rd);
    }

    /**
     * @test
     */
    public function table_wr_collection()
    {
        $w = new AMQPWriter();
        $w->write_table(
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
            $r->read_table(true)->getNativeData()
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
        $max = PHP_INT_SIZE == 8 ? 4294967295 : PHP_INT_MAX;

        return [
            [0],
            [1],
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
        return [
            [-PHP_INT_MAX - 1],
            [-PHP_INT_MAX],
            [PHP_INT_MAX - 1],
            [PHP_INT_MAX],
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
            ['0'],
            ['1'],
            ['2'],
            ['2147483646'],
            ['2147483647'],
            ['2147483648'],
            ['4294967294'],
            ['4294967295'],
            ['4294967296'],
            ['9223372036854775805'],
            ['9223372036854775806'],
            ['9223372036854775807'],
        ];
    }

    public function shortstrWrData() {
        return [
            ['a'],
            ['üıß∑œ´®†¥¨πøˆ¨¥†®'],
        ];
    }

    public function longstrWrData() {
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

        $this->assertEquals($value, $readValue);
    }
}

<?php

namespace PhpAmqpLib\Tests\Unit\Wire;

use PhpAmqpLib\Wire;
use PHPUnit\Framework\TestCase;

class AMQPCollectionTest extends TestCase
{
    /**
     * @test
     */
    public function encode_080()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_080);

        $a = new Wire\AMQPArray([
            1,
            (int) -2147483648,
            (int) 2147483647,
            -2147483649,
            2147483648,
            true,
            false,
            ['foo' => 'bar'],
            ['foo'],
            [],
            new \DateTime('2009-02-13 23:31:30'),
            (class_exists('DateTimeImmutable')
                ? new \DateTimeImmutable('2009-02-13 23:31:30')
                : new \DateTime('2009-02-13 23:31:30')
            ),
        ]);

        $this->assertEquals(
            [
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                    1,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                    -2147483648,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                    2147483647,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                    -2147483649,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                    2147483648,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                    1,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                    0,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'),
                    [
                        'foo' => [
                            Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                            'bar',
                        ],
                    ],
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'),
                    [
                        0 => [
                            Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                            'foo',
                        ],
                    ],
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'),
                    [],
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('T'),
                    1234567890,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('T'),
                    1234567890,
                ],
            ],
            $this->getEncodedRawData($a)
        );

        $eData = $this->getEncodedRawData($a, false);

        $this->assertEquals(true, $eData[7][1] instanceof Wire\AMQPTable);
        $this->assertEquals(true, $eData[8][1] instanceof Wire\AMQPTable);
        $this->assertEquals(true, $eData[9][1] instanceof Wire\AMQPTable);
    }

    /**
     * @test
     */
    public function encode_091()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_091);

        $a = new Wire\AMQPArray([
            1,
            (int) -2147483648,
            (int) 2147483647,
            -2147483649,
            2147483648,
            true,
            false,
            ['foo' => 'bar'],
            ['foo'],
            [],
        ]);

        $is64 = PHP_INT_SIZE == 8;

        $this->assertEquals(
            [
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                    1,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                    -2147483648,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                    2147483647,
                ],
                [
                    $is64
                        ? Wire\AMQPAbstractCollection::getDataTypeForSymbol('L')
                        : Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                    -2147483649,
                ],
                [
                    $is64
                        ? Wire\AMQPAbstractCollection::getDataTypeForSymbol('L')
                        : Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                    2147483648,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('t'),
                    true,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('t'),
                    false,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'),
                    [
                        'foo' => [
                            Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                            'bar',
                        ],
                    ],
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('A'),
                    [
                        [
                            Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                            'foo',
                        ],
                    ],
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('A'),
                    [],
                ],
            ],
            $this->getEncodedRawData($a)
        );

        $eData = $this->getEncodedRawData($a, false);

        $this->assertEquals(true, $eData[7][1] instanceof Wire\AMQPTable);
        $this->assertEquals(true, $eData[8][1] instanceof Wire\AMQPArray);
        $this->assertEquals(true, $eData[9][1] instanceof Wire\AMQPArray);
    }

    /**
     * @test
     */
    public function encode_rabbit()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_RBT);

        $a = new Wire\AMQPArray([
            1,
            (int) -2147483648,
            (int) 2147483647,
            -2147483649,
            2147483648,
            true,
            false,
            ['foo' => 'bar'],
            ['foo'],
            [],
        ]);

        $is64 = PHP_INT_SIZE == 8;

        $this->assertEquals(
            [
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                    1,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                    -2147483648,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                    2147483647,
                ],
                [
                    $is64
                        ? Wire\AMQPAbstractCollection::getDataTypeForSymbol('l')
                        : Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                    -2147483649,
                ],
                [
                    $is64
                        ? Wire\AMQPAbstractCollection::getDataTypeForSymbol('l')
                        : Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                    2147483648,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('t'),
                    true,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('t'),
                    false,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'),
                    [
                        'foo' => [
                            Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                            'bar'
                        ],
                    ],
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('A'),
                    [
                        [
                            Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                            'foo',
                        ],
                    ],
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('A'),
                    [],
                ],
            ],
            $this->getEncodedRawData($a)
        );

        $eData = $this->getEncodedRawData($a, false);

        $this->assertEquals(true, $eData[7][1] instanceof Wire\AMQPTable);
        $this->assertEquals(true, $eData[8][1] instanceof Wire\AMQPArray);
        $this->assertEquals(true, $eData[9][1] instanceof Wire\AMQPArray);
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPOutOfBoundsException
     */
    public function encode_unknown_data_type()
    {
        $a = new Wire\AMQPArray(array(new \stdClass()));
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPOutOfRangeException
     */
    public function push_unsupported_data_type_080()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_080);
        $a = new Wire\AMQPArray();
        
        $a->push(12345, Wire\AMQPArray::T_INT_LONGLONG);
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPOutOfRangeException
     */
    public function push_unsupported_data_type_091()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_091);
        $a = new Wire\AMQPArray();

        $a->push(12345, 'foo');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPOutOfRangeException
     */
    public function push_unsupported_data_type_rabbit()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_RBT);
        $a = new Wire\AMQPArray();

        $a->push(12345, Wire\AMQPArray::T_INT_LONGLONG_U);
    }

    /**
     * @test
     */
    public function push_with_type()
    {
        $a = new Wire\AMQPArray();

        $a->push(576, Wire\AMQPArray::T_INT_LONG);
        $a->push('foo', Wire\AMQPArray::T_STRING_LONG);
        $a->push(new Wire\AMQPTable(['foo' => 'bar']));
        $a->push(new Wire\AMQPArray(['bar']));

        $this->assertEquals(
            [
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                    576,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                    'foo',
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'),
                    [
                        'foo' => [
                            Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                            'bar',
                        ],
                    ],
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('A'),
                    [
                        [
                            Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                            'bar',
                        ],
                    ],
                ],
            ],
            $this->getEncodedRawData($a)
        );
    }

    /**
     * @test
     */
    public function conflicting_field_symbols()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_091);

        $a = new Wire\AMQPArray();
        $a->push(576, Wire\AMQPArray::T_INT_SHORT);
        $a->push(1234567, Wire\AMQPArray::T_INT_LONGLONG);

        $this->assertEquals(
            [
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('U'),
                    576,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('L'),
                    1234567,
                ],
            ],
            $this->getEncodedRawData($a)
        );

        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_RBT);

        $a = new Wire\AMQPArray();
        $a->push(576, Wire\AMQPArray::T_INT_SHORT);
        $a->push(1234567, Wire\AMQPArray::T_INT_LONGLONG);

        $this->assertEquals(
            [
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('s'),
                    576,
                ],
                [
                    Wire\AMQPAbstractCollection::getDataTypeForSymbol('l'),
                    1234567,
                ],
            ],
            $this->getEncodedRawData($a)
        );
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     * @expectedExceptionMessage Table key must be non-empty string up to 128 chars in length
     */
    public function set_empty_key()
    {
        $t = new Wire\AMQPTable();

        $t->set('', 'foo');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     * @expectedExceptionMessage Table key must be non-empty string up to 128 chars in length
     */
    public function set_long_key()
    {
        $t = new Wire\AMQPTable();

        $t->set(str_repeat('a', 129), 'bar');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function push_mismatched_type()
    {
        $a = new Wire\AMQPArray();

        $a->push(new Wire\AMQPArray(), Wire\AMQPArray::T_TABLE);
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     * @expectedExceptionMessage Arrays must be passed as AMQPArray instance
     */
    public function push_raw_array_with_type()
    {
        $a = new Wire\AMQPArray();

        $a->push(array(), Wire\AMQPArray::T_ARRAY);
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     * @expectedExceptionMessage Tables must be passed as AMQPTable instance
     */
    public function push_raw_table_with_type()
    {
        $a = new Wire\AMQPArray();

        $a->push(array(), Wire\AMQPArray::T_TABLE);
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     * @expectedExceptionMessage Decimal values must be instance of AMQPDecimal
     */
    public function push_float_with_decimal_type()
    {
        $a = new Wire\AMQPArray();

        $a->push(35.2, Wire\AMQPArray::T_DECIMAL);
    }

    /**
     * @test
     */
    public function array_round_trip_080()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_080);
        $a = new Wire\AMQPArray($this->getTestDataSrc());

        $this->assertEquals(array_values($this->getTestDataCmp080()), $a->getNativeData());
    }

    /**
     * @test
     */
    public function array_round_trip_091()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_091);
        $a = new Wire\AMQPArray($this->getTestDataSrc());

        $this->assertEquals(array_values($this->getTestDataCmp()), $a->getNativeData());
    }

    /**
     * @test
     */
    public function array_round_trip_rabbit()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_RBT);
        $a = new Wire\AMQPArray($this->getTestDataSrc());

        $this->assertEquals(array_values($this->getTestDataCmp()), $a->getNativeData());
    }

    /**
     * @test
     */
    public function table_round_trip_080()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_080);
        $a = new Wire\AMQPTable($this->getTestDataSrc());

        $this->assertEquals($this->getTestDataCmp080(), $a->getNativeData());
    }

    /**
     * @test
     */
    public function table_round_trip_091()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_091);
        $a = new Wire\AMQPTable($this->getTestDataSrc());

        $this->assertEquals($this->getTestDataCmp(), $a->getNativeData());
    }

    /**
     * @test
     */
    public function table_round_trip_rabbit()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_RBT);
        $a = new Wire\AMQPTable($this->getTestDataSrc());

        $this->assertEquals($this->getTestDataCmp(), $a->getNativeData());
    }

    /**
     * @test
     */
    public function iterator()
    {
        $d = [
            'a' => 1,
            'b' => -2147,
            'c' => [
                'foo' => 'bar',
            ],
            'd' => true,
            'e' => false,
        ];

        $ed = [
            'a' => [
                Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                1,
            ],
            'b' => [
                Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'),
                -2147,
            ],
            'c' => [
                Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'),
                [
                    'foo' => [
                        Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'),
                        'bar',
                    ],
                ],
            ],
            'd' => [
                Wire\AMQPAbstractCollection::getDataTypeForSymbol('t'),
                true,
            ],
            'e' => [
                Wire\AMQPAbstractCollection::getDataTypeForSymbol('t'),
                false,
            ],
        ];

        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_091);
        $a = new Wire\AMQPTable($d);

        foreach ($a as $key => $val) {
            if (!isset($d[$key])) {
                $this->fail('Unknown key: '.$key);
            }
            $this->assertEquals(
                $ed[$key],
                $val[1] instanceof Wire\AMQPAbstractCollection
                    ? [$val[0], $this->getEncodedRawData($val[1])]
                    : $val
            );
        }
    }

    protected function setProtoVersion($proto)
    {
        $r = new \ReflectionProperty('\\PhpAmqpLib\\Wire\\AMQPAbstractCollection', '_protocol');
        $r->setAccessible(true);
        $r->setValue(null, $proto);
    }

    protected function getEncodedRawData(Wire\AMQPAbstractCollection $c, $recursive = true)
    {
        $r = new \ReflectionProperty($c, 'data');
        $r->setAccessible(true);
        $data = $r->getValue($c);
        unset($r);

        if ($recursive) {
            foreach ($data as &$v) {
                if ($v[1] instanceof Wire\AMQPAbstractCollection) {
                    $v[1] = $this->getEncodedRawData($v[1]);
                }
            }
            unset($v);
        }

        return $data;
    }

    protected function getTestDataSrc()
    {
        return [
            'long' => 12345,
            'long_neg' => -12345,
            'longlong' => 3000000000,
            'longlong_neg' => -3000000000,
            'float_low' => (float) 9.2233720368548,
            'float_high' => (float) 9223372036854800000,
            'bool_true' => true,
            'bool_false' => false,
            'void' => null,
            'array' => [
                1,
                2,
                3,
                'foo',
                ['bar' => 'baz'],
                ['boo', false, 5],
                true,
                null
            ],
            'array_empty' => [],
            'table' => [
                'foo' => 'bar',
                'baz' => 'boo',
                'bool' => true,
                'tbl' => ['bar' => 'baz'],
                'arr' => ['boo', false, 5],
            ],
            'table_num' => [
                1 => 5,
                3 => 'foo',
                786 => 674,
            ],
            'array_nested' => [
                1,
                [
                    2,
                    [
                        3,
                        [4],
                    ],
                ],
            ],
            'table_nested' => [
                'i' => 1,
                'n' => [
                    'i' => 2,
                    'n' => [
                        'i' => 3,
                        'n' => [
                            'i' => 4,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * The only purpose of this *Cmp / *Cmp080 shit is to pass tests on travis's ancient phpunit 3.7.38.
     */
    protected function getTestDataCmp()
    {
        return [
            'long' => 12345,
            'long_neg' => -12345,
            'longlong' => 3000000000,
            'longlong_neg' => -3000000000,
            'float_low' => (string) (float) 9.2233720368548,
            'float_high' => (string) (float) 9223372036854800000,
            'bool_true' => true,
            'bool_false' => false,
            'void' => null,
            'array' => [
                1,
                2,
                3,
                'foo',
                ['bar' => 'baz'],
                ['boo', false, 5],
                true,
                null
            ],
            'array_empty' => [],
            'table' => [
                'foo' => 'bar',
                'baz' => 'boo',
                'bool' => true,
                'tbl' => ['bar' => 'baz'],
                'arr' => ['boo', false, 5],
            ],
            'table_num' => [
                1 => 5,
                3 => 'foo',
                786 => 674,
            ],
            'array_nested' => [
                1,
                [
                    2,
                    [
                        3,
                        [4],
                    ],
                ],
            ],
            'table_nested' => [
                'i' => 1,
                'n' => [
                    'i' => 2,
                    'n' => [
                        'i' => 3,
                        'n' => [
                            'i' => 4,
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getTestDataCmp080()
    {
        return [
            'long' => 12345,
            'long_neg' => -12345,
            'longlong' => (string) 3000000000,
            'longlong_neg' => (string) -3000000000,
            'float_low' => (string) (float) 9.2233720368548,
            'float_high' => (string) (float) 9223372036854800000,
            'bool_true' => 1,
            'bool_false' => 0,
            'void' => '',
            'array' => [
                1,
                2,
                3,
                'foo',
                ['bar' => 'baz'],
                ['boo', 0, 5],
                1,
                '',
            ],
            'array_empty' => [],
            'table' => [
                'foo' => 'bar',
                'baz' => 'boo',
                'bool' => 1,
                'tbl' => ['bar' => 'baz'],
                'arr' => ['boo', 0, 5],
            ],
            'table_num' => [
                1 => 5,
                3 => 'foo',
                786 => 674,
            ],
            'array_nested' => [
                1,
                [
                    2,
                    [
                        3,
                        [4],
                    ],
                ],
            ],
            'table_nested' => [
                'i' => 1,
                'n' => [
                    'i' => 2,
                    'n' => [
                        'i' => 3,
                        'n' => [
                            'i' => 4,
                        ],
                    ],
                ],
            ],
        ];
    }
}

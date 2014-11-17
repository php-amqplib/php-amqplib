<?php

namespace PhpAmqpLib\Tests\Unit;

use PhpAmqpLib\Wire;

class AMQPCollectionTest extends \PHPUnit_Framework_TestCase
{

    public function testEncode080()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_080);
        $a = new Wire\AMQPArray(array(1, (int) -2147483648, (int) 2147483647, -2147483649, 2147483648, true, false, array('foo' => 'bar'), array('foo'), array()));

        $this->assertEquals(
            array(
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), 1),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), -2147483648),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), 2147483647),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), -2147483649),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), 2147483648),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), 1),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), 0),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'), array('foo' => array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), 'bar'))),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'), array(0 => array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), 'foo'))),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'), array())
            ),
            $this->getEncodedRawData($a)
        );

        $eData = $this->getEncodedRawData($a, false);
        $this->assertEquals(true, $eData[7][1] instanceof Wire\AMQPTable);
        $this->assertEquals(true, $eData[8][1] instanceof Wire\AMQPTable);
        $this->assertEquals(true, $eData[9][1] instanceof Wire\AMQPTable);
    }



    public function testEncode091()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_091);
        $a = new Wire\AMQPArray(array(1, (int) -2147483648, (int) 2147483647, -2147483649, 2147483648, true, false, array('foo' => 'bar'), array('foo'), array()));

        $is64 = PHP_INT_SIZE == 8;
        $this->assertEquals(
            array(
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), 1),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), -2147483648),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), 2147483647),
                array($is64 ? Wire\AMQPAbstractCollection::getDataTypeForSymbol('L') : Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), -2147483649),
                array($is64 ? Wire\AMQPAbstractCollection::getDataTypeForSymbol('L') : Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), 2147483648),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('t'), true),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('t'), false),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'), array('foo' => array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), 'bar'))),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('A'), array(array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), 'foo'))),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('A'), array())
            ),
            $this->getEncodedRawData($a)
        );

        $eData = $this->getEncodedRawData($a, false);
        $this->assertEquals(true, $eData[7][1] instanceof Wire\AMQPTable);
        $this->assertEquals(true, $eData[8][1] instanceof Wire\AMQPArray);
        $this->assertEquals(true, $eData[9][1] instanceof Wire\AMQPArray);
    }



    public function testEncodeRabbit()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_RBT);
        $a = new Wire\AMQPArray(array(1, (int) -2147483648, (int) 2147483647, -2147483649, 2147483648, true, false, array('foo' => 'bar'), array('foo'), array()));

        $is64 = PHP_INT_SIZE == 8;
        $this->assertEquals(
            array(
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), 1),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), -2147483648),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), 2147483647),
                array($is64 ? Wire\AMQPAbstractCollection::getDataTypeForSymbol('l') : Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), -2147483649),
                array($is64 ? Wire\AMQPAbstractCollection::getDataTypeForSymbol('l') : Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), 2147483648),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('t'), true),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('t'), false),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'), array('foo' => array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), 'bar'))),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('A'), array(array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), 'foo'))),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('A'), array())
            ),
            $this->getEncodedRawData($a)
        );

        $eData = $this->getEncodedRawData($a, false);
        $this->assertEquals(true, $eData[7][1] instanceof Wire\AMQPTable);
        $this->assertEquals(true, $eData[8][1] instanceof Wire\AMQPArray);
        $this->assertEquals(true, $eData[9][1] instanceof Wire\AMQPArray);
    }



    public function testEncodeUnknownDatatype()
    {
        $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPOutOfBoundsException');
        $a = new Wire\AMQPArray(array(new \stdClass()));
        $this->fail('Unknown data type not detected!');
    }



    public function testPushUnsupportedDataType080()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_080);

        $a = new Wire\AMQPArray();
        $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPOutOfRangeException');
        $a->push(12345, Wire\AMQPArray::T_INT_LONGLONG);
        $this->fail('Unsupported data type not detected!');
    }



    public function testPushUnsupportedDataType091()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_091);

        $a = new Wire\AMQPArray();
        $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPOutOfRangeException');
        $a->push(12345, 'foo');
        $this->fail('Unsupported data type not detected!');
    }



    public function testPushUnsupportedDataTypeRabbit()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_RBT);

        $a = new Wire\AMQPArray();
        $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPOutOfRangeException');
        $a->push(12345, Wire\AMQPArray::T_INT_LONGLONG_U);
        $this->fail('Unsupported data type not detected!');
    }



    public function testPushWithType()
    {
        $a = new Wire\AMQPArray();
        $a->push(576, Wire\AMQPArray::T_INT_LONG);
        $a->push('foo', Wire\AMQPArray::T_STRING_LONG);
        $a->push(new Wire\AMQPTable(array('foo' => 'bar')));
        $a->push(new Wire\AMQPArray(array('bar')));

        $this->assertEquals(
            array(
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), 576),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), 'foo'),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'), array('foo' => array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), 'bar'))),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('A'), array(array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), 'bar')))
            ),
            $this->getEncodedRawData($a)
        );
    }



    public function testConflictingFieldSymbols()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_091);

        $a = new Wire\AMQPArray();
        $a->push(576, Wire\AMQPArray::T_INT_SHORT);
        $a->push(1234567, Wire\AMQPArray::T_INT_LONGLONG);

        $this->assertEquals(
            array(
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('U'), 576),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('L'), 1234567)
            ),
            $this->getEncodedRawData($a)
        );


        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_RBT);

        $a = new Wire\AMQPArray();
        $a->push(576, Wire\AMQPArray::T_INT_SHORT);
        $a->push(1234567, Wire\AMQPArray::T_INT_LONGLONG);

        $this->assertEquals(
            array(
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('s'), 576),
                array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('l'), 1234567)
            ),
            $this->getEncodedRawData($a)
        );
    }



    public function testSetEmptyKey()
    {
        $t = new Wire\AMQPTable();

        $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException', 'Table key must be non-empty string up to 128 chars in length');
        $t->set('', 'foo');
        $this->fail('Empty table key not detected!');
    }



    public function testSetLongKey()
    {
        $t = new Wire\AMQPTable();
        $t->set(str_repeat('a', 128), 'foo');

        $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException', 'Table key must be non-empty string up to 128 chars in length');
        $t->set(str_repeat('a', 129), 'bar');
        $this->fail('Excessive key length not detected!');
    }



    public function testPushMismatchedType()
    {
        $a = new Wire\AMQPArray();
        $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException');
        $a->push(new Wire\AMQPArray(), Wire\AMQPArray::T_TABLE);
        $this->fail('Mismatched data type not detected!');
    }



    public function testPushRawArrayWithType()
    {
        $a = new Wire\AMQPArray();
        $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException', 'Arrays must be passed as AMQPArray instance');
        $a->push(array(), Wire\AMQPArray::T_ARRAY);
        $this->fail('Raw array data not detected!');
    }



    public function testPushRawTableWithType()
    {
        $a = new Wire\AMQPArray();
        $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException', 'Tables must be passed as AMQPTable instance');
        $a->push(array(), Wire\AMQPArray::T_TABLE);
        $this->fail('Raw table data not detected!');
    }



    public function testPushFloatWithDecimalType()
    {
        $a = new Wire\AMQPArray();
        $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException', 'Decimal values must be instance of AMQPDecimal');
        $a->push(35.2, Wire\AMQPArray::T_DECIMAL);
        $this->fail('Wrong decimal data not detected!');
    }



    public function testArrayRoundTrip080()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_080);

        $a = new Wire\AMQPArray($this->getTestDataSrc());
        $this->assertEquals(array_values($this->getTestDataCmp080()), $a->getNativeData());
    }



    public function testArrayRoundTrip091()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_091);

        $a = new Wire\AMQPArray($this->getTestDataSrc());
        $this->assertEquals(array_values($this->getTestDataCmp()), $a->getNativeData());
    }



    public function testArrayRoundTripRabbit()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_RBT);

        $a = new Wire\AMQPArray($this->getTestDataSrc());
        $this->assertEquals(array_values($this->getTestDataCmp()), $a->getNativeData());
    }



    public function testTableRoundTrip080()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_080);

        $a = new Wire\AMQPTable($this->getTestDataSrc());
        $this->assertEquals($this->getTestDataCmp080(), $a->getNativeData());
    }



    public function testTableRoundTrip091()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_091);

        $a = new Wire\AMQPTable($this->getTestDataSrc());
        $this->assertEquals($this->getTestDataCmp(), $a->getNativeData());
    }



    public function testTableRoundTripRabbit()
    {
        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_RBT);

        $a = new Wire\AMQPTable($this->getTestDataSrc());
        $this->assertEquals($this->getTestDataCmp(), $a->getNativeData());
    }



    protected function getTestDataSrc()
    {
        return array(
            'long' => 12345,
            'long_neg' => -12345,
            'longlong' => 3000000000,
            'longlong_neg' => -3000000000,
            'float_low' => (float) 9.2233720368548,
            'float_high' => (float) 9223372036854800000,
            'bool_true' => true,
            'bool_false' => false,
            'void' => null,
            'array' => array(1, 2, 3, 'foo', array('bar' => 'baz'), array('boo', false, 5), true, null),
            'array_empty' => array(),
            'table' => array('foo' => 'bar', 'baz' => 'boo', 'bool' => true, 'tbl' => array('bar' => 'baz'), 'arr' => array('boo', false, 5)),
            'table_num' => array(1 => 5, 3 => 'foo', 786 => 674),
            'array_nested' => array(1, array(2, array(3, array(4)))),
            'table_nested' => array('i' => 1, 'n' => array('i' => 2, 'n' => array('i' => 3, 'n' => array('i' => 4))))
        );
    }



    /**
     * The only purpose of this *Cmp / *Cmp080 shit is to pass tests on travis's ancient phpunit 3.7.38
     */
    protected function getTestDataCmp()
    {
        return array(
            'long' => 12345,
            'long_neg' => -12345,
            'longlong' => 3000000000,
            'longlong_neg' => -3000000000,
            'float_low' => (string) (float) 9.2233720368548,
            'float_high' => (string) (float) 9223372036854800000,
            'bool_true' => true,
            'bool_false' => false,
            'void' => null,
            'array' => array(1, 2, 3, 'foo', array('bar' => 'baz'), array('boo', false, 5), true, null),
            'array_empty' => array(),
            'table' => array('foo' => 'bar', 'baz' => 'boo', 'bool' => true, 'tbl' => array('bar' => 'baz'), 'arr' => array('boo', false, 5)),
            'table_num' => array(1 => 5, 3 => 'foo', 786 => 674),
            'array_nested' => array(1, array(2, array(3, array(4)))),
            'table_nested' => array('i' => 1, 'n' => array('i' => 2, 'n' => array('i' => 3, 'n' => array('i' => 4))))
        );
    }



    protected function getTestDataCmp080()
    {
        return array(
            'long' => 12345,
            'long_neg' => -12345,
            'longlong' => (string) 3000000000,
            'longlong_neg' => (string) -3000000000,
            'float_low' => (string) (float) 9.2233720368548,
            'float_high' => (string) (float) 9223372036854800000,
            'bool_true' => 1,
            'bool_false' => 0,
            'void' => '',
            'array' => array(1, 2, 3, 'foo', array('bar' => 'baz'), array('boo', 0, 5), 1, ''),
            'array_empty' => array(),
            'table' => array('foo' => 'bar', 'baz' => 'boo', 'bool' => 1, 'tbl' => array('bar' => 'baz'), 'arr' => array('boo', 0, 5)),
            'table_num' => array(1 => 5, 3 => 'foo', 786 => 674),
            'array_nested' => array(1, array(2, array(3, array(4)))),
            'table_nested' => array('i' => 1, 'n' => array('i' => 2, 'n' => array('i' => 3, 'n' => array('i' => 4))))
        );
    }



    public function testIterator()
    {
        $d = array('a' => 1, 'b' => -2147, 'c' => array('foo' => 'bar'), 'd' => true, 'e' => false);
        $ed = array(
            'a' => array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), 1),
            'b' => array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('I'), -2147),
            'c' => array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('F'), array('foo' => array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('S'), 'bar'))),
            'd' => array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('t'), true),
            'e' => array(Wire\AMQPAbstractCollection::getDataTypeForSymbol('t'), false)
        );

        $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTOCOL_091);
        $a = new Wire\AMQPTable($d);

        foreach ($a as $key => $val) {
            if (!isset($d[$key])) {
                $this->fail('Unknown key: ' . $key);
            }
            $this->assertEquals($ed[$key], $val[1] instanceof Wire\AMQPAbstractCollection ? array($val[0], $this->getEncodedRawData($val[1])) : $val);
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
}

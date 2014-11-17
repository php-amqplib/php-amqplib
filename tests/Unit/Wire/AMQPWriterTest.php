<?php

namespace PhpAmqpLib\Tests\Unit;

use PhpAmqpLib\Wire\AMQPArray;
use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Wire\AMQPWriter;

class AMQPWriterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var AMQPWriter
     */
    protected $_writer;



    public function setUp()
    {
        $this->setProtoVersion(AMQPArray::PROTOCOL_091);
        $this->_writer = new AMQPWriter();
    }



    public function tearDown()
    {
        $this->setProtoVersion(AMQPArray::PROTOCOL_RBT);
        $this->_writer = null;
    }



    protected function setProtoVersion($proto)
    {
        $r = new \ReflectionProperty('\\PhpAmqpLib\\Wire\\AMQPAbstractCollection', '_protocol');
        $r->setAccessible(true);
        $r->setValue(null, $proto);
    }



    public function testWriteArray()
    {
        $this->_writer->write_array(array(
            'rabbit@localhost',
            'hare@localhost',
            42,
            true
        ));

        $out = $this->_writer->getvalue();

        $this->assertEquals(51, mb_strlen($out, 'ASCII'));

        $expected = "\x00\x00\x00\x2fS\x00\x00\x00\x10rabbit@localhostS\x00\x00\x00\x0Ehare@localhostI\x00\x00\x00\x2at\x01";

        $this->assertEquals($expected, $out);
    }



    public function testWriteAMQPArray()
    {
        $this->_writer->write_array(
            new AMQPArray(
                array(
                    'rabbit@localhost',
                    'hare@localhost',
                    42,
                    true
                )
            )
        );
        $this->assertEquals(
            "\x00\x00\x00\x2fS\x00\x00\x00\x10rabbit@localhostS\x00\x00\x00\x0Ehare@localhostI\x00\x00\x00\x2at\x01",
            $this->_writer->getvalue()
        );
    }



    public function testWriteTable()
    {
        $this->_writer->write_table(array(
            'x-foo' => array('S', 'bar'),
            'x-bar' => array('A', array('baz', 'qux')),
            'x-baz' => array('I', 42),
            'x-true' => array('t', true),
            'x-false' => array('t', false),
            'x-shortshort' => array('b', -5),
            'x-shortshort-u' => array('B', 5),
            'x-short' => array('U', -1024),
            'x-short-u' => array('u', 125),
            'x-short-str' => array('s', 'foo')
        ));

        $out = $this->_writer->getvalue();


        $expected = "\x00\x00\x00\x90\x05x-fooS\x00\x00\x00\x03bar\x05x-barA\x00\x00\x00\x10S\x00\x00\x00\x03bazS\x00\x00\x00\x03qux\x05x-bazI\x00\x00\x00\x2a\x06x-truet\x01\x07x-falset\x00".
                    "\X0cx-shortshortb\xfb\x0ex-shortshort-uB\x05\x07x-shortU\xfc\x00\x09x-short-uu\x00\x7d\x0bx-short-strs\x03foo";

        $this->assertEquals($expected, $out);
    }



    public function testWriteAMQPTable()
    {
        $t = new AMQPTable();
        $t->set('x-foo', 'bar', AMQPTable::T_STRING_LONG);
        $t->set('x-bar', new AMQPArray(array('baz', 'qux')));
        $t->set('x-baz', 42, AMQPTable::T_INT_LONG);
        $t->set('x-true', true, AMQPTable::T_BOOL);
        $t->set('x-false', false, AMQPTable::T_BOOL);
        $t->set('x-shortshort', -5, AMQPTable::T_INT_SHORTSHORT);
        $t->set('x-shortshort-u', 5, AMQPTable::T_INT_SHORTSHORT_U);
        $t->set('x-short', -1024, AMQPTable::T_INT_SHORT);
        $t->set('x-short-u', 125, AMQPTable::T_INT_SHORT_U);
        $t->set('x-short-str', 'foo', AMQPTable::T_STRING_SHORT);

        $this->_writer->write_table($t);
        $this->assertEquals(
            "\x00\x00\x00\x90\x05x-fooS\x00\x00\x00\x03bar\x05x-barA\x00\x00\x00\x10S\x00\x00\x00\x03bazS\x00\x00\x00\x03qux\x05x-bazI\x00\x00\x00\x2a\x06x-truet\x01\x07x-falset\x00" .
            "\X0cx-shortshortb\xfb\x0ex-shortshort-uB\x05\x07x-shortU\xfc\x00\x09x-short-uu\x00\x7d\x0bx-short-strs\x03foo",
            $this->_writer->getvalue()
        );
    }



    public function testWriteTableThrowsExceptionOnInvalidType()
    {
        $this->setExpectedException('PhpAmqpLib\Exception\AMQPOutOfRangeException');

        $this->_writer->write_table(array(
            'x-foo' => array('_', 'bar'),
        ));
    }
}

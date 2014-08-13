<?php

namespace PhpAmqpLib\Tests\Unit;

use PhpAmqpLib\Wire\AMQPWriter;

class AMQPWriterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var AMQPWriter
     */
    protected $_writer;



    public function setUp()
    {
        $this->_writer = new AMQPWriter();
    }



    public function tearDown()
    {
        $this->_writer = null;
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



    public function testWriteTable()
    {
        $this->_writer->write_table(array(
            'x-foo' => array('S', 'bar'),
            'x-bar' => array('A', array('baz', 'qux')),
            'x-baz' => array('I', 42),
            'x-true' => array('t', true),
            'x-false' => array('t', false)
        ));

        $out = $this->_writer->getvalue();


        $expected = "\x00\x00\x00\x47\x05x-fooS\x00\x00\x00\x03bar\x05x-barA\x00\x00\x00\x10S\x00\x00\x00\x03bazS\x00\x00\x00\x03qux\x05x-bazI\x00\x00\x00\x2a\x06x-truet\x01\x07x-falset\x00";

        $this->assertEquals($expected, $out);
    }



    public function testWriteTableThrowsExceptionOnInvalidType()
    {
        $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException', "Invalid type '_'");

        $this->_writer->write_table(array(
            'x-foo' => array('_', 'bar'),
        ));
    }
}

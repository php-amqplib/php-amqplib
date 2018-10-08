<?php

namespace PhpAmqpLib\Tests\Unit\Wire;

use PhpAmqpLib\Wire\AMQPArray;
use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Wire\AMQPWriter;
use PHPUnit\Framework\TestCase;

class AMQPWriterTest extends TestCase
{
    protected $writer;

    public function setUp()
    {
        $this->setProtoVersion(AMQPArray::PROTOCOL_091);
        $this->writer = new AMQPWriter();
    }

    public function tearDown()
    {
        $this->setProtoVersion(AMQPArray::PROTOCOL_RBT);
        $this->writer = null;
    }

    /**
     * @test
     */
    public function write_array()
    {
        $this->writer->write_array([
            'rabbit@localhost',
            'hare@localhost',
            42,
            true
        ]);
        $out = $this->writer->getvalue();
        $expected = "\x00\x00\x00\x2fS\x00\x00\x00\x10rabbit@localhostS\x00\x00\x00\x0Ehare@localhostI\x00\x00\x00\x2at\x01";

        $this->assertEquals(51, mb_strlen($out, 'ASCII'));
        $this->assertEquals($expected, $out);
    }

    /**
     * @test
     */
    public function write_AMQP_array()
    {
        $this->writer->write_array(
            new AMQPArray([
                    'rabbit@localhost',
                    'hare@localhost',
                    42,
                    true
            ])
        );

        $this->assertEquals(
            "\x00\x00\x00\x2fS\x00\x00\x00\x10rabbit@localhostS\x00\x00\x00\x0Ehare@localhostI\x00\x00\x00\x2at\x01",
            $this->writer->getvalue()
        );
    }

    /**
     * @test
     */
    public function write_table()
    {
        $this->writer->write_table([
            'x-foo' => ['S', 'bar'],
            'x-bar' => ['A', ['baz', 'qux']],
            'x-baz' => ['I', 42],
            'x-true' => ['t', true],
            'x-false' => ['t', false],
            'x-shortshort' => ['b', -5],
            'x-shortshort-u' => ['B', 5],
            'x-short' => ['U', -1024],
            'x-short-u' => ['u', 125],
            'x-short-str' => ['s', 'foo'],
            'x-bytes' => array('x', 'foobar'),
        ]);
        $out = $this->writer->getvalue();
        $expected = "\x00\x00\x00\xa3\x05x-fooS\x00\x00\x00\x03bar\x05x-barA\x00\x00\x00\x10S\x00\x00\x00\x03bazS\x00\x00\x00\x03qux\x05x-bazI\x00\x00\x00\x2a\x06x-truet\x01\x07x-falset\x00" .
            "\X0cx-shortshortb\xfb\x0ex-shortshort-uB\x05\x07x-shortU\xfc\x00\x09x-short-uu\x00\x7d\x0bx-short-strs\x03foo\x07x-bytesx\x00\x00\x00\x06foobar";
        $this->assertEquals($expected, $out);
    }

    /**
     * @test
     */
    public function write_AMQP_table()
    {
        $t = new AMQPTable();
        $t->set('x-foo', 'bar', AMQPTable::T_STRING_LONG);
        $t->set('x-bar', new AMQPArray(['baz', 'qux']));
        $t->set('x-baz', 42, AMQPTable::T_INT_LONG);
        $t->set('x-true', true, AMQPTable::T_BOOL);
        $t->set('x-false', false, AMQPTable::T_BOOL);
        $t->set('x-shortshort', -5, AMQPTable::T_INT_SHORTSHORT);
        $t->set('x-shortshort-u', 5, AMQPTable::T_INT_SHORTSHORT_U);
        $t->set('x-short', -1024, AMQPTable::T_INT_SHORT);
        $t->set('x-short-u', 125, AMQPTable::T_INT_SHORT_U);
        $t->set('x-short-str', 'foo', AMQPTable::T_STRING_SHORT);
        $this->writer->write_table($t);

        $this->assertEquals(
            "\x00\x00\x00\x90\x05x-fooS\x00\x00\x00\x03bar\x05x-barA\x00\x00\x00\x10S\x00\x00\x00\x03bazS\x00\x00\x00\x03qux\x05x-bazI\x00\x00\x00\x2a\x06x-truet\x01\x07x-falset\x00" .
            "\X0cx-shortshortb\xfb\x0ex-shortshort-uB\x05\x07x-shortU\xfc\x00\x09x-short-uu\x00\x7d\x0bx-short-strs\x03foo",
            $this->writer->getvalue()
        );
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPOutOfRangeException
     */
    public function write_table_with_invalid_type()
    {
        $this->writer->write_table([
            'x-foo' => ['_', 'bar'],
        ]);
    }

    protected function setProtoVersion($proto)
    {
        $r = new \ReflectionProperty('\\PhpAmqpLib\\Wire\\AMQPAbstractCollection', '_protocol');
        $r->setAccessible(true);
        $r->setValue(null, $proto);
    }
}

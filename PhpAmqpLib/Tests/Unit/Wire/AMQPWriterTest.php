<?php

namespace PhpAmqpLib\Tests\Unit;

use PhpAmqpLib\Wire\AMQPReader;
use PhpAmqpLib\Wire\AMQPWriter;


class AMQPWriterTest extends \PHPUnit_Framework_TestCase
{
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
            'hare@localhost'
        ));
        
        $out = $this->_writer->getvalue();
        
        $this->assertEquals(44, strlen($out));
        
        $expected = "\x00\x00\x00(S\x00\x00\x00\x10rabbit@localhostS\x00\x00\x00\x0Ehare@localhost";
        
        $this->assertEquals($expected, $out);
    }
}

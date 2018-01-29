<?php

namespace PhpAmqpLib\Tests\Unit\Wire;

use PhpAmqpLib\Wire\AMQPDecimal;
use PHPUnit\Framework\TestCase;

class AMQPDecimalTest extends TestCase
{
    public function testDecimal()
    {
        $decimal = new AMQPDecimal(100,2);

        $this->assertEquals($decimal->asBCvalue(),1);
        $this->assertEquals($decimal->getN(),100);
        $this->assertEquals($decimal->getE(),2);
    }

    /**
     * @expectedException \PhpAmqpLib\Exception\AMQPOutOfBoundsException
     */
    public function testDecimalWithNegativeValue()
    {
        new AMQPDecimal(100,-1);
    }

}

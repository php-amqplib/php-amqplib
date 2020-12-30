<?php

namespace PhpAmqpLib\Tests\Unit\Wire;

use PhpAmqpLib\Wire\AMQPDecimal;
use PHPUnit\Framework\TestCase;

class AMQPDecimalTest extends TestCase
{
    /**
     * @test
     */
    public function asBcValue()
    {
        $decimal = new AMQPDecimal(100, 2);

        $this->assertEquals($decimal->asBCvalue(), 1);
    }

    /**
     * @test
     */
    public function getN()
    {
        $decimal = new AMQPDecimal(100, 2);

        $this->assertEquals($decimal->getN(), 100);
    }

    /**
     * @test
     */
    public function getE()
    {
        $decimal = new AMQPDecimal(100, 2);

        $this->assertEquals($decimal->getE(), 2);
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPOutOfBoundsException
     */
    public function negative_value()
    {
        new AMQPDecimal(100, -1);
    }
}

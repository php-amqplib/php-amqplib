<?php

namespace PhpAmqpLib\Tests\Unit\Wire;

use PhpAmqpLib\Wire\AMQPDecimal;
use PHPUnit\Framework\TestCase;

class AMQPDecimalTest extends TestCase
{
    /**
     * @test
     */
    public function as_bc_value()
    {
        $decimal = new AMQPDecimal(100, 2);

        $this->assertEquals($decimal->asBCvalue(), 1);
    }

    /**
     * @test
     */
    public function get_n()
    {
        $decimal = new AMQPDecimal(100, 2);

        $this->assertEquals($decimal->getN(), 100);
    }

    /**
     * @test
     */
    public function get_e()
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

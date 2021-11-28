<?php

namespace PhpAmqpLib\Tests\Unit\Connection;

use PhpAmqpLib\Tests\Unit\Test\TestConnection;
use PHPUnit\Framework\TestCase;

class AbstractConnectionTest extends TestCase
{
    /**
     * @test
     */
    public function connection_argument_io_not_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument $io cannot be null');

        new TestConnection('', '');
    }
}

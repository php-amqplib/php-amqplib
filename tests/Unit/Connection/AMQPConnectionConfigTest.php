<?php

namespace PhpAmqpLib\Tests\Unit\Connection;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PHPUnit\Framework\TestCase;

class AMQPConnectionConfigTest extends TestCase
{
    /**
     * @test
     */
    public function check_default_connection_name()
    {
        $config = new AMQPConnectionConfig();
        $this->assertEquals('', $config->getConnectionName());
    }

    /**
     * @test
     */
    public function set_get_connection_name()
    {
        $config = new AMQPConnectionConfig();
        $name = 'Connection_01';
        $config->setConnectionName($name);
        $this->assertEquals($name, $config->getConnectionName());
    }
}
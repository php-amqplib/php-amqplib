<?php

namespace PhpAmqpLib\Tests\Unit\Connection;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
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

    /**
     * @test
     */
    public function connection_login_method_external(): void
    {
        $config = new AMQPConnectionConfig();
        $config->setIsLazy(true);
        $config->setUser('');
        $config->setPassword('');
        $config->setLoginMethod(AMQPConnectionConfig::AUTH_EXTERNAL);
        $config->setLoginResponse($response = 'response');

        $connection = AMQPConnectionFactory::create($config);

        $reflection = new \ReflectionClass($connection);
        $property = $reflection->getProperty('login_response');
        $property->setAccessible(true);
        self::assertEquals($response, $property->getValue($connection));
    }
}

<?php

namespace PhpAmqpLib\Tests\Unit\Connection;

use PhpAmqpLib\Channel\Frame;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Tests\Unit\Test\TestConnection;
use PhpAmqpLib\Wire\IO\AbstractIO;
use PHPUnit\Framework\MockObject\MockObject;
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

    /**
     * @test
     */
    public function close_channels_if_disconnected(): void
    {
        $ioMock = $this->createMock(AbstractIO::class);
        $config = new AMQPConnectionConfig();
        $config->setIsLazy(false);

        $args = [
            $user = null,
            $password = null,
            $vhost = '/',
            $insist = false,
            $login_method = 'AMQPLAIN',
            $login_response = null,
            $locale = 'en_US',
            $ioMock,
            $heartbeat = 0,
            $connectionTimeout = 0,
            $channelRpcTimeout = 0.0,
            $config
        ];

        /** @var MockObject&AbstractConnection $connection */
        $connection = $this->getMockForAbstractClass(
            AbstractConnection::class,
            $args,
            $mockClassName = '',
            $callOriginalConstructor = true,
            $callOriginalClone = true,
            $callAutoload = true,
            $mockedMethods = [
                'connect',
                'send_channel_method_frame',
                'wait_channel',
            ]
        );

        // Emulate channel.open_ok
        $payload = pack('n2', 20, 11);
        $connection
            ->method('wait_channel')
            ->willReturn(new Frame(
                Frame::TYPE_METHOD,
                1,
                mb_strlen($payload),
                $payload
            ));
        $newChannel = $connection->channel(1);
        $this->assertTrue($newChannel->is_open());

        // Emulate error to call do_close
        $ioMock
            ->expects($this->once())
            ->method('select')
            ->willThrowException(new AMQPConnectionClosedException('test'));
        $ioMock
            ->expects($this->once())
            ->method('close');

        $exception = null;
        try {
            $connection->select(3);
        } catch (AMQPConnectionClosedException $exception) {
        }

        $this->assertInstanceOf(AMQPConnectionClosedException::class, $exception);
        $this->assertEquals('test', $exception->getMessage());

        // After do_close is called, channel must be inactive too
        $this->assertFalse($newChannel->is_open());
    }
}

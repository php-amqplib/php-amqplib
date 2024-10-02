<?php

namespace PhpAmqpLib\Tests\Unit\Channel;

use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionBlockedException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Unit\Test\BufferIO;
use PhpAmqpLib\Tests\Unit\Test\TestChannel;
use PhpAmqpLib\Tests\Unit\Test\TestConnection;
use PhpAmqpLib\Wire\AMQPWriter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AMQPChannelTest extends TestCase
{
    /**
     * @test
     */
    public function blocked_connection_exception_on_publish()
    {
        $this->expectException(AMQPConnectionBlockedException::class);
        $connection = new TestConnection('user', 'pass', '/', false, 'PLAIN', null, '', new BufferIO());
        $connection->setIsBlocked(true);
        $channel = new TestChannel($connection, 1);
        $channel->basic_publish(new AMQPMessage());
    }

    /**
     * @test
     * @dataProvider basic_consume_invalid_arguments_provider
     * @param mixed[] $arguments
     * @param string $expectedException
     */
    public function basic_consume_invalid_arguments($arguments, $expectedException)
    {
        $this->expectException($expectedException);
        $connection = new TestConnection('user', 'pass', '/', false, 'PLAIN', null, '', new BufferIO());
        $channel = new TestChannel($connection, 1);
        $channel->basic_consume(...$arguments);
    }

    /**
     * @test
     */
    public function publish_batch_failed_connection(): void
    {
        $connection = new TestConnection('user', 'pass', '/', false, 'PLAIN', null, '', new BufferIO());

        $channel = new TestChannel($connection, 1);
        $channel->close_connection();

        $message = new AMQPMessage();
        $channel->batch_basic_publish($message, 'exchange', 'routing_key');

        $this->expectException(AMQPChannelClosedException::class);
        $this->expectExceptionMessage('Channel connection is closed.');

        $channel->publish_batch();
    }

    /**
     * @test
     */
    public function publish_batch_opened_connection(): void
    {
        $mock_builder = $this->getMockBuilder(TestConnection::class)
            ->disableOriginalConstructor();

        $methods_to_mock = [
            'prepare_content',
            'prepare_channel_method_frame',
            'write',
        ];

        if (!method_exists($mock_builder, 'onlyMethods')) {
            $mock_builder->setMethods($methods_to_mock);
        } else {
            $mock_builder->onlyMethods($methods_to_mock);
        }

        $connection_mock = $mock_builder->getMock();
        $channel = new TestChannel($connection_mock, 1);

        $message = new AMQPMessage();
        $writer = new AMQPWriter();

        $channel->batch_basic_publish($message, 'exchange', 'routing_key');

        $connection_mock->expects(self::once())
            ->method('prepare_content');

        $connection_mock->expects(self::once())
            ->method('prepare_channel_method_frame')
            ->willReturn($writer);

        $connection_mock->expects(self::once())
            ->method('write');

        $channel->publish_batch();
    }

    /**
     * @test
     */
    public function close_if_disconnected_false(): void
    {
        $mockBuilder = $this->getMockBuilder(TestConnection::class)
            ->disableOriginalConstructor();

        $methodsToMock = [
            'isConnected',
        ];

        if (!method_exists($mockBuilder, 'onlyMethods')) {
            $mockBuilder->setMethods($methodsToMock);
        } else {
            $mockBuilder->onlyMethods($methodsToMock);
        }

        /** @var MockObject&TestConnection $connectionMock */
        $connectionMock = $mockBuilder->getMock();
        $channel = new TestChannel($connectionMock, 1);

        $connectionMock->expects(self::once())
            ->method('isConnected')
            ->willReturn(true);

        // Should return `false` because $this->connection->isConnected()
        $firstResult = $channel->closeIfDisconnected();
        $this->assertFalse($firstResult);
    }

    /**
     * @test
     */
    public function close_if_disconnected_true(): void
    {
        $mockBuilder = $this->getMockBuilder(TestConnection::class)
            ->disableOriginalConstructor();

        $methodsToMock = [
            'isConnected',
        ];

        if (!method_exists($mockBuilder, 'onlyMethods')) {
            $mockBuilder->setMethods($methodsToMock);
        } else {
            $mockBuilder->onlyMethods($methodsToMock);
        }

        /** @var MockObject&TestConnection $connectionMock */
        $connectionMock = $mockBuilder->getMock();
        $channel = new TestChannel($connectionMock, 1);

        $connectionMock->expects(self::once())
            ->method('isConnected')
            ->willReturn(false);

        // Should return true
        $firstResult = $channel->closeIfDisconnected();
        $this->assertTrue($firstResult);

        // Should return `false` because no $this->connection
        $secondResult = $channel->closeIfDisconnected();
        $this->assertFalse($secondResult);
    }

    public function basic_consume_invalid_arguments_provider()
    {
        return [
            [
                [
                    '',
                    '',
                    false,
                    false,
                    false,
                    false,
                    'non_callable variable',
                ],
                \InvalidArgumentException::class,
            ],
            [
                [
                    '',
                    '',
                    false,
                    false,
                    false,
                    true,
                    'sleep',
                ],
                \InvalidArgumentException::class,
            ]
        ];
    }
}

<?php

namespace PhpAmqpLib\Tests\Unit\Channel;

use PhpAmqpLib\Exception\AMQPConnectionBlockedException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Unit\Test\BufferIO;
use PhpAmqpLib\Tests\Unit\Test\TestChannel;
use PhpAmqpLib\Tests\Unit\Test\TestConnection;
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

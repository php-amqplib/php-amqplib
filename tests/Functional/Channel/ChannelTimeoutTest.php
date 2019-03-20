<?php
namespace PhpAmqpLib\Tests\Unit\Channel;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Helper\MiscHelper;
use PhpAmqpLib\Wire\IO\AbstractIO;
use PHPUnit\Framework\TestCase;

class ChannelTimeoutTest extends TestCase
{

    /** @var float $channel_rpc_timeout */
    private $channel_rpc_timeout;

    /** @var int $channel_rpc_timeout */
    private $channel_rpc_timeout_seconds;

    /** @var int $channel_rpc_timeout */
    private $channel_rpc_timeout_microseconds;

    /** @var AbstractIO|\PHPUnit_Framework_MockObject_MockObject $io */
    private $io;

    /** @var AbstractConnection|\PHPUnit_Framework_MockObject_MockObject $connection */
    private $connection;

    /** @var AMQPChannel $channel */
    private $channel;

    protected function setUp()
    {
        parent::setUp();

        $this->channel_rpc_timeout = 3.5;

        list( $this->channel_rpc_timeout_seconds, $this->channel_rpc_timeout_microseconds ) =
            MiscHelper::splitSecondsMicroseconds( $this->channel_rpc_timeout );

        $this->io = $this->getMockBuilder('\PhpAmqpLib\Wire\IO\StreamIO')
            ->setConstructorArgs(array(HOST, PORT, 3, 3, null, false, 0))
            ->setMethods(array('select'))
            ->getMock();
        $this->connection = $this->getMockBuilder('\PhpAmqpLib\Connection\AbstractConnection')
            ->setConstructorArgs(array(USER, PASS, '/', false, 'AMQPLAIN', null, 'en_US', $this->io, 0, 0, $this->channel_rpc_timeout))
            ->setMethods(array())
            ->getMockForAbstractClass();

        $this->channel = $this->connection->channel();
    }

    /**
     * @test
     *
     * @dataProvider provide_operations
     * @param string $operation
     * @param array $args
     *
     * @covers \PhpAmqpLib\Channel\AMQPChannel::exchange_declare
     * @covers \PhpAmqpLib\Channel\AMQPChannel::queue_declare
     * @covers \PhpAmqpLib\Channel\AMQPChannel::confirm_select
     *
     * @expectedException \PhpAmqpLib\Exception\AMQPTimeoutException
     * @expectedExceptionMessage The connection timed out after 3.5 sec while awaiting incoming data
     */
    public function should_throw_exception_for_basic_operations_when_timeout_exceeded($operation, $args)
    {
        // simulate blocking on the I/O level
        $this->io->expects($this->any())
            ->method('select')
            ->with($this->channel_rpc_timeout_seconds, $this->channel_rpc_timeout_microseconds)
            ->willReturn(0);

        call_user_func_array(array($this->channel, $operation), $args);
    }

    public function provide_operations()
    {
        return array(
            array('exchange_declare', array('test_ex', 'fanout')),
            array('queue_declare', array('test_queue')),
            array('confirm_select', array()),
        );
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->channel->close();
        $this->channel = null;
        $this->connection->close();
        $this->connection = null;
    }
}

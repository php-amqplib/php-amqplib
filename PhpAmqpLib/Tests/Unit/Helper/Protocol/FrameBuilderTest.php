<?php

namespace PhpAmqpLib\Tests\Unit\Helper\Writer;

use PhpAmqpLib\Helper\Protocol\FrameBuilder;

class FrameBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->frameBuilder = new FrameBuilder();
    }

    public function testChannelClose()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00";
        $args = $this->frameBuilder->channelClose(0, "", 0, 0);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00\x00\x05error\x00\x00\x00\x00";
        $args = $this->frameBuilder->channelClose(0, "error", 0, 0);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00\x00\x05error\x00\x14\x00\x28";
        $args = $this->frameBuilder->channelClose(0, "error", 20, 40);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testFlow()
    {
        $expected = "\x01";
        $args = $this->frameBuilder->flow(true);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00";
        $args = $this->frameBuilder->flow(false);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testXFlowOk()
    {
        $expected = "\x01";
        $args = $this->frameBuilder->xFlowOk(true);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00";
        $args = $this->frameBuilder->xFlowOk(false);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testXOpen()
    {
        $expected = "\x03foo";
        $args = $this->frameBuilder->xOpen("foo");
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00";
        $args = $this->frameBuilder->xOpen("");
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testAccessRequest()
    {
        $expected = "\x01/\x00";
        $args = $this->frameBuilder->accessRequest("/", false, false, false, false, false);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x04/foo\x00";
        $args = $this->frameBuilder->accessRequest("/foo", false, false, false, false, false);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testExchangeDeclare()
    {
        $expected = "\x00\x00\x03foo\x06direct\x00\x00\x00\x00\x00";
        $args = $this->frameBuilder->exchangeDeclare(
                                            'foo', 'direct', false,
                                            false, false,
                                            false, false,
                                            array(), 0
                                         );
       $this->assertEquals($expected, $args->getvalue());
    }

    public function testExchangeDelete()
    {
        $expected = "\x00\x00\x03foo\x00";
        $args = $this->frameBuilder->exchangeDelete('foo', false, false, 0);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testQueueBind()
    {
        $expected = "\x00\x00\x03foo\x03bar\x03baz\x00\x00\x00\x00\x00";
        $args = $this->frameBuilder->queueBind('foo', 'bar', 'baz', false, array(), 0);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testQueueUnbind()
    {
        $expected = "\x00\x00\x03foo\x03bar\x03baz\x00\x00\x00\x00";
        $args = $this->frameBuilder->queueUnbind('foo', 'bar', 'baz', array(), 0);
        $this->assertEquals($expected, $args->getvalue());
    }


    public function testQueueDeclare()
    {
        $expected = "\x00\x00\x03foo\x00\x00\x00\x00\x00";
        $args = $this->frameBuilder->queueDeclare(
                                            'foo', false,
                                            false, false,
                                            false, false,
                                            array(), 0
                                         );
       $this->assertEquals($expected, $args->getvalue());
    }

    public function testQueueDelete()
    {
        $expected = "\x00\x00\x03foo\x00";
        $args = $this->frameBuilder->queueDelete('foo', false, false, false, 0);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testQueuePurge()
    {
        $expected = "\x00\x00\x03foo\x00";
        $args = $this->frameBuilder->queuePurge('foo', false, 0);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicAck()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00\x01\x00";
        $args = $this->frameBuilder->basicAck(1, false);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicCancel()
    {
        $expected = "\x03foo\x00";
        $args = $this->frameBuilder->basicCancel('foo', false);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicConsume()
    {
        $expected = "\x00\x00\x03foo\x03bar\x00";
        $args = $this->frameBuilder->basicConsume('foo', 'bar', false, false, false, false, 0);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicGet()
    {
        $expected = "\x00\x00\x03foo\x00";
        $args = $this->frameBuilder->basicGet('foo', false, 0);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicPublish()
    {
        $expected = "\x00\x00\x03foo\x03bar\x00";
        $args = $this->frameBuilder->basicPublish('foo', 'bar', false, false, 0);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicQos()
    {
        $expected = "\x00\x00\x00\xA\x00\x01\x00";
        $args = $this->frameBuilder->basicQos(10, 1, false);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicRecover()
    {
        $expected = "\x01";
        $args = $this->frameBuilder->basicRecover(true);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00";
        $args = $this->frameBuilder->basicRecover(false);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicReject()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00\x01\x01";
        $args = $this->frameBuilder->basicReject(1, true);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00\x00\x00\x00\x00\x00\x00\x01\x00";
        $args = $this->frameBuilder->basicReject(1, false);
        $this->assertEquals($expected, $args->getvalue());
    }
}
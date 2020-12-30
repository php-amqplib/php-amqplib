<?php

namespace PhpAmqpLib\Tests\Unit\Helper\Protocol;

use PhpAmqpLib\Helper\Protocol\Protocol091;
use PHPUnit\Framework\TestCase;

class Protocol091Test extends TestCase
{
    protected $protocol091;

    public function setUp()
    {
        $this->protocol091 = new Protocol091();
    }

    /**
     * @test
     */
    public function channelClose()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->channelClose(0, '', 0, 0);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function channelCloseError()
    {
        $expected = "\x00\x00\x05error\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->channelClose(0, 'error', 0, 0);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00\x00\x05error\x00\x14\x00\x28";
        list($class_id, $method_id, $args) = $this->protocol091->channelClose(0, 'error', 20, 40);
        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function channelFlowTrue()
    {
        $expected = "\x01";
        list($class_id, $method_id, $args) = $this->protocol091->channelFlow(true);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function channelFlowFalse()
    {
        $expected = "\x00";
        list($class_id, $method_id, $args) = $this->protocol091->channelFlow(false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function channelOpenFoo()
    {
        $expected = "\x03foo";
        list($class_id, $method_id, $args) = $this->protocol091->channelOpen('foo');

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function channelOpenEmptyString()
    {
        $expected = "\x00";
        list($class_id, $method_id, $args) = $this->protocol091->channelOpen('');

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function accessRequest()
    {
        $expected = "\x01/\x00";
        list($class_id, $method_id, $args) = $this->protocol091->accessRequest('/', false, false, false, false, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function accessRequestFoo()
    {
        $expected = "\x04/foo\x00";
        list($class_id, $method_id, $args) = $this->protocol091->accessRequest(
            '/foo',
            false,
            false,
            false,
            false,
            false
        );

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function exchangeDeclare()
    {
        $expected = "\x00\x00\x03foo\x06direct\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->exchangeDeclare(
            0,
            'foo',
            'direct',
            false,
            false,
            false,
            false,
            false,
            []
        );

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function exchangeDelete()
    {
        $expected = "\x00\x00\x03foo\x00";
        list($class_id, $method_id, $args) = $this->protocol091->exchangeDelete(0, 'foo', false, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function exchangeBind()
    {
        $expected = "\x00\x00\x03foo\x03bar\x03baz\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->exchangeBind(0, 'foo', 'bar', 'baz', false, []);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function exchangeUnbind()
    {
        $expected = "\x00\x00\x03foo\x03bar\x03baz\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->exchangeUnbind(0, 'foo', 'bar', 'baz', false, []);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function queueBind()
    {
        $expected = "\x00\x00\x03foo\x03bar\x03baz\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->queueBind(0, 'foo', 'bar', 'baz', false, []);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function queueUnbind()
    {
        $expected = "\x00\x00\x03foo\x03bar\x03baz\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->queueUnbind(0, 'foo', 'bar', 'baz', []);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function queueDeclare()
    {
        $expected = "\x00\x00\x03foo\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->queueDeclare(
            0,
            'foo',
            false,
            false,
            false,
            false,
            false,
            []
        );

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function queueDelete()
    {
        $expected = "\x00\x00\x03foo\x00";
        list($class_id, $method_id, $args) = $this->protocol091->queueDelete(0, 'foo', false, false, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function queuePurge()
    {
        $expected = "\x00\x00\x03foo\x00";
        list($class_id, $method_id, $args) = $this->protocol091->queuePurge(0, 'foo', false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basicAck()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00\x01\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicAck(1, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basicCancel()
    {
        $expected = "\x03foo\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicCancel('foo', false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basicConsume()
    {
        $expected = "\x00\x00\x03foo\x03bar\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicConsume(
            0,
            'foo',
            'bar',
            false,
            false,
            false,
            false
        );

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basicGet()
    {
        $expected = "\x00\x00\x03foo\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicGet(0, 'foo', false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basicPublish()
    {
        $expected = "\x00\x00\x03foo\x03bar\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicPublish(0, 'foo', 'bar', false, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basicQos()
    {
        $expected = "\x00\x00\x00\xA\x00\x01\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicQos(10, 1, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basicRecoverTrue()
    {
        $expected = "\x01";
        list($class_id, $method_id, $args) = $this->protocol091->basicRecover(true);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basicRecoverFalse()
    {
        $expected = "\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicRecover(false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basicReject1True()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00\x01\x01";
        list($class_id, $method_id, $args) = $this->protocol091->basicReject(1, true);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basicReject1False()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00\x01\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicReject(1, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function connectionBlocked()
    {
        $expected = 'Low on memory';
        list($class_id, $method_id, $args) = $this->protocol091->connectionBlocked($expected);

        $this->assertEquals($class_id, 10);
        $this->assertEquals($method_id, 60);
        $this->assertEquals($expected, trim($args->getValue()));
    }
}

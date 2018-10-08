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
    public function channel_close()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->channelClose(0, '', 0, 0);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function channel_close_error()
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
    public function channel_flow_true()
    {
        $expected = "\x01";
        list($class_id, $method_id, $args) = $this->protocol091->channelFlow(true);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function channel_flow_false()
    {
        $expected = "\x00";
        list($class_id, $method_id, $args) = $this->protocol091->channelFlow(false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function channel_open_foo()
    {
        $expected = "\x03foo";
        list($class_id, $method_id, $args) = $this->protocol091->channelOpen('foo');

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function channel_open_empty_string()
    {
        $expected = "\x00";
        list($class_id, $method_id, $args) = $this->protocol091->channelOpen('');

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function access_request()
    {
        $expected = "\x01/\x00";
        list($class_id, $method_id, $args) = $this->protocol091->accessRequest('/', false, false, false, false, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function access_request_foo()
    {
        $expected = "\x04/foo\x00";
        list($class_id, $method_id, $args) = $this->protocol091->accessRequest(
            '/foo', false, false, false, false, false
        );

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function exchange_declare()
    {
        $expected = "\x00\x00\x03foo\x06direct\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->exchangeDeclare(
            0, 'foo', 'direct', false,
            false, false,
            false, false,
            []
        );

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function exchange_delete()
    {
        $expected = "\x00\x00\x03foo\x00";
        list($class_id, $method_id, $args) = $this->protocol091->exchangeDelete(0, 'foo', false, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function exchange_bind()
    {
        $expected = "\x00\x00\x03foo\x03bar\x03baz\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->exchangeBind(0, 'foo', 'bar', 'baz', false, []);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function exchange_unbind()
    {
        $expected = "\x00\x00\x03foo\x03bar\x03baz\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->exchangeUnbind(0, 'foo', 'bar', 'baz', false, []);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function queue_bind()
    {
        $expected = "\x00\x00\x03foo\x03bar\x03baz\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->queueBind(0, 'foo', 'bar', 'baz', false, []);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function queue_unbind()
    {
        $expected = "\x00\x00\x03foo\x03bar\x03baz\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->queueUnbind(0, 'foo', 'bar', 'baz', []);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function queue_declare()
    {
        $expected = "\x00\x00\x03foo\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->queueDeclare(
            0, 'foo', false,
            false, false,
            false, false,
            []
        );

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function queue_delete()
    {
        $expected = "\x00\x00\x03foo\x00";
        list($class_id, $method_id, $args) = $this->protocol091->queueDelete(0, 'foo', false, false, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function queue_purge()
    {
        $expected = "\x00\x00\x03foo\x00";
        list($class_id, $method_id, $args) = $this->protocol091->queuePurge(0, 'foo', false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basic_ack()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00\x01\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicAck(1, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basic_cancel()
    {
        $expected = "\x03foo\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicCancel('foo', false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basic_consume()
    {
        $expected = "\x00\x00\x03foo\x03bar\x00\x00\x00\x00\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicConsume(
            0, 'foo', 'bar', false, false, false, false
        );

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basic_get()
    {
        $expected = "\x00\x00\x03foo\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicGet(0, 'foo', false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basic_publish()
    {
        $expected = "\x00\x00\x03foo\x03bar\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicPublish(0, 'foo', 'bar', false, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basic_qos()
    {
        $expected = "\x00\x00\x00\xA\x00\x01\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicQos(10, 1, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basic_recover_true()
    {
        $expected = "\x01";
        list($class_id, $method_id, $args) = $this->protocol091->basicRecover(true);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basic_recover_false()
    {
        $expected = "\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicRecover(false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basic_reject_1_true()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00\x01\x01";
        list($class_id, $method_id, $args) = $this->protocol091->basicReject(1, true);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function basic_reject_1_false()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00\x01\x00";
        list($class_id, $method_id, $args) = $this->protocol091->basicReject(1, false);

        $this->assertEquals($expected, $args->getvalue());
    }

    /**
     * @test
     */
    public function connection_blocked()
    {
        $expected = 'Low on memory';
        list($class_id, $method_id, $args) = $this->protocol091->connectionBlocked($expected);

        $this->assertEquals($class_id, 10);
        $this->assertEquals($method_id, 60);
        $this->assertEquals($expected, trim($args->getValue()));
    }
}

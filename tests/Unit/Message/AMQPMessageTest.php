<?php

namespace PhpAmqpLib\Tests\Unit\Message;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPReader;
use PHPUnit\Framework\TestCase;

class AMQPMessageTest extends TestCase
{
    /**
     * @dataProvider propertiesData
     * @test
     */
    public function serialize_properties(array $expected, array $properties)
    {
        $reader = new AMQPReader(null);
        $message = new AMQPMessage('', $properties);

        $encodedData = $message->serialize_properties();
        $reader->reuse($encodedData);
        $message->load_properties($reader);
        $props = $message->get_properties();

        if (isset($props['application_headers'])) {
            $props['application_headers'] = $props['application_headers']->getNativeData();
        }

        $this->assertEquals($expected, $props);
    }

    /**
     * @test
     */
    public function get_and_set_body()
    {
        $message = new AMQPMessage('');
        $message->setBody('body');
        $message->setIsTruncated(true);
        $message->content_encoding = 'shortstr';

        $this->assertEquals($message->getBody(), 'body');
        $this->assertTrue($message->isTruncated());
        $this->assertEquals($message->getContentEncoding(), 'shortstr');
    }

    public function propertiesData()
    {
        return [
            [
            ['priority' => 1, 'timestamp' => time()],
            ['priority' => 1, 'timestamp' => time()],
        ],
            [
            ['message_id' => '5414cfa74899a'],
            ['message_id' => '5414cfa74899a'],
        ],
            [
            ['message_id' => 0],
            ['message_id' => 0],
        ],
            [
            [],
            ['timestamp' => null],
        ],
            [
            [],
            ['priority' => null],
        ],
            [
            ['priority' => 0],
            ['priority' => 0],
        ],
            [
            ['priority' => false],
            ['priority' => false],
        ],
            [
            ['priority' => '0'],
            ['priority' => '0'],
        ],
            [
            ['application_headers' => ['x-foo' => '']],
            ['application_headers' => ['x-foo' => ['S', '']]],
        ],
            [
            ['application_headers' => ['x-foo' => '']],
            ['application_headers' => ['x-foo' => ['S', null]]],
        ],
            [
            ['application_headers' => ['x-foo' => 0]],
            ['application_headers' => ['x-foo' => ['I', 0]]],
        ],
            [
            ['application_headers' => ['x-foo' => 1]],
            ['application_headers' => ['x-foo' => ['I', true]]],
        ],
            [
            ['application_headers' => ['x-foo' => 0]],
            ['application_headers' => ['x-foo' => ['I', '0']]],
        ],
            [
            ['application_headers' => ['x-foo' => []]],
            ['application_headers' => ['x-foo' => ['A', []]]],
        ],
            [
            ['application_headers' => ['x-foo' => [null]]],
            ['application_headers' => ['x-foo' => ['A', [null]]]],
        ],
        ];
    }
}

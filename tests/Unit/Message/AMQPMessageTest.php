<?php
namespace PhpAmqpLib\Tests\Unit\Message;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPReader;

class AMQPMessageTest extends \PHPUnit_Framework_TestCase
{
    public function propertiesDataProvider()
    {
        return array(
            array(array('priority' => 1, 'timestamp' => time()), array('priority' => 1, 'timestamp' => time())),
            array(array('message_id' => '5414cfa74899a'), array('message_id' => '5414cfa74899a')),
            array(array('message_id' => 0), array('message_id' => 0)),
            array(array(), array('timestamp' => null)),
            array(array(), array('priority' => null)),
            array(array('priority' => 0), array('priority' => 0)),
            array(array('priority' => false), array('priority' => false)),
            array(array('priority' => '0'), array('priority' => '0')),
            array(array('application_headers' => array('x-foo' => array('S', ''))), array('application_headers' => array('x-foo' => array('S', '')))),
            array(array('application_headers' => array('x-foo' => array('S', null))), array('application_headers' => array('x-foo' => array('S', null)))),
            array(array('application_headers' => array('x-foo' => array('I', 0))), array('application_headers' => array('x-foo' => array('I', 0)))),
            array(array('application_headers' => array('x-foo' => array('I', true))), array('application_headers' => array('x-foo' => array('I', true)))),
            array(array('application_headers' => array('x-foo' => array('I', '0'))), array('application_headers' => array('x-foo' => array('I', '0')))),
            array(array('application_headers' => array('x-foo' => array('A', array()))), array('application_headers' => array('x-foo' => array('A', array())))),
            array(array('application_headers' => array('x-foo' => array('A', array()))), array('application_headers' => array('x-foo' => array('A', array(null))))),
        );
    }

    /**
     * @dataProvider propertiesDataProvider
     */
    public function testSerializeProperties(array $expected, array $properties)
    {
        /** @var AMQPReader $reader */
        $reader = new AMQPReader(null);
        /** @var AMQPMessage $message */
        $message = new AMQPMessage('', $properties);
        /** @var string $encodedData */
        $encodedData = $message->serialize_properties();

        // Bypasses the network part and injects the encoded data into the reader
        $reader->reuse($encodedData);
        // Injects the reader into the message
        $message->load_properties($reader);

        $this->assertEquals($expected, $message->get_properties());
    }
}

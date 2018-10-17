<?php

namespace PhpAmqpLib\Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;

/**
 * Test the library properties
 */
class LibraryPropertiesTest extends TestCase
{
    /**
     * Client properties.
     *
     * https://www.rabbitmq.com/amqp-0-9-1-reference.html#connection.start-ok.client-properties
     * The properties SHOULD contain at least these fields:
     *    "product", giving the name of the client product,
     *    "version", giving the name of the client version,
     *    "platform", giving the name of the operating system,
     *    "copyright", if appropriate, and
     *    "information", giving other general information.
     *
     * @test
     */
    public function requiredProperties()
    {
        $connection = $this->getMockBuilder('\PhpAmqpLib\Connection\AMQPStreamConnection')
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock();

        $properties = $connection->getLibraryProperties();

        // Assert that the library properties method returns an array
        $this->assertInternalType('array', $properties);

        // Ensure that the required properties exist in the array
        $this->assertArrayHasKey('product', $properties);
        $this->assertArrayHasKey('version', $properties);
        $this->assertArrayHasKey('platform', $properties);
        $this->assertArrayHasKey('copyright', $properties);
        $this->assertArrayHasKey('information', $properties);
    }

    /**
     * AMQPWriter::table_write expects values given with data types and values
     * ensure each property is an array with the first value being a data type
     *
     * @test
     */
    public function propertyTypes()
    {
        $connection = $this->getMockBuilder('\PhpAmqpLib\Connection\AMQPStreamConnection')
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock();

        $properties = $connection->getLibraryProperties();

        // Assert that the library properties method returns an array
        $this->assertInternalType('array', $properties);

        // Iterate array checking each value is suitable
        foreach ($properties as $property) {
            // Property should be an array with exactly 2 properties
            $this->assertInternalType('array', $property);
            $this->assertCount(2, $property);
            // Retreive the datatype and ensure it matches our signature
            $dataType = $property[0];
            $this->assertInternalType('string', $dataType);
            $this->assertStringMatchesFormat('%c', $dataType);
        }
    }
}

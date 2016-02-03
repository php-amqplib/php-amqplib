<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Connection\AMQPStreamConnection;

class StreamPublishConsumeTest extends AbstractPublishConsumeTest
{
    protected function createConnection()
    {
        return new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
    }

    public function testStreamConnectionThrowsExceptionOnConnectionTimeout()
    {
        $this->setExpectedException(
            '\PhpAmqpLib\Exception\AMQPRuntimeException',
            'Error reading data. Connection timed out.'
        );
        new AMQPStreamConnection(HOST, 15672, USER, PASS, VHOST);
    }
}

<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPProtocolException;

class Bug49Test extends \PHPUnit_Framework_TestCase
{
    protected $exchange_name = 'test_exchange';
    protected $queue_name1 = null;
    protected $queue_name2 = null;
    protected $q1msgs = 0;

    public function testDeclaration()
    {
        $connection = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
        $channel = $connection->channel();

        try {
            $channel->queue_declare('pretty.queue', true, true);
            $this->fail('Should have raised an exception');
        } catch (AMQPProtocolException $e) {
            if ($e->getCode() == 404) {
                $channel->queue_declare('pretty.queue', false, true);
            } else {
                $this->fail('Should have raised a 404 Error');
            }
        }
    }
}

<?php

namespace PhpAmqpLib\Tests\Unit\Test;

use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;

class TestChannel extends AMQPChannel
{
    /**
     * @param AbstractConnection $connection
     * @param int|null           $channelId
     * @param bool               $autoDecode
     * @param int|float          $channelRpcTimeout
     */
    public function __construct($connection, $channelId = null, $autoDecode = true, $channelRpcTimeout = 0)
    {
        if ($channelId === null) {
            $channelId = $connection->getFreeChannelId();
        }

        AbstractChannel::__construct($connection, $channelId);

        $this->debug->debugMsg('using channel_id: ' . $channelId);

        $this->autoDecode = $autoDecode;
        $this->channelRpcTimeout = $channelRpcTimeout;
    }
}

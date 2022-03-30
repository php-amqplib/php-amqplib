<?php

namespace PhpAmqpLib\Tests\Unit\Test;

use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;

class TestChannel extends AMQPChannel
{
    /**
     * @param AbstractConnection $connection
     * @param int|null $channel_id
     * @param bool $auto_decode
     * @param int|float $channel_rpc_timeout
     */
    public function __construct($connection, $channel_id = null, $auto_decode = true, $channel_rpc_timeout = 0)
    {
        if ($channel_id === null) {
            $channel_id = $connection->get_free_channel_id();
        }

        AbstractChannel::__construct($connection, $channel_id);

        $this->debug->debug_msg('using channel_id: ' . $channel_id);

        $this->auto_decode = $auto_decode;
        $this->channel_rpc_timeout = $channel_rpc_timeout;
    }

    public function close_connection(): void {
        $this->do_close();
    }
}

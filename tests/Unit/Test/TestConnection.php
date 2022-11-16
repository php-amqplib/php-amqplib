<?php

namespace PhpAmqpLib\Tests\Unit\Test;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Wire\AMQPBufferReader;

class TestConnection extends AbstractConnection
{
    /**
     * @inheritDoc
     */
    public function connectOnConstruct(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isConnected()
    {
        return true;
    }

    public function setIsBlocked($blocked = true)
    {
        if ($blocked) {
            $this->connection_blocked(new AMQPBufferReader(hex2bin('0120')));
        } else {
            $this->connection_unblocked();
        }
    }
}

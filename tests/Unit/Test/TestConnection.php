<?php

namespace PhpAmqpLib\Tests\Unit\Test;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Wire\AMQPReader;

class TestConnection extends AbstractConnection
{
    /**
     * @inheritDoc
     */
    public function connectOnConstruct()
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
            $this->connection_blocked(new AMQPReader(hex2bin('0120')));
        } else {
            $this->connection_unblocked();
        }
    }
}

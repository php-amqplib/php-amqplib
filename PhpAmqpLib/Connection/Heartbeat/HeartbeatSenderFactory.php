<?php
namespace PhpAmqpLib\Connection\Heartbeat;

use PhpAmqpLib\Connection\AbstractConnection;

class HeartbeatSenderFactory
{
    /**
     * @param AbstractConnection $connection
     * @param bool $pcntl_based
     * @return HeartbeatSenderInterface
     */
    public static function getSender(AbstractConnection $connection, $pcntl_based)
    {
        if ($pcntl_based) {
            return new PCNTLHeartbeatSender($connection);
        } else {
            return new NullHeartbeatSender();
        }
    }
}

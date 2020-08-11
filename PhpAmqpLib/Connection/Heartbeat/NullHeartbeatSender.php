<?php
namespace PhpAmqpLib\Connection\Heartbeat;

final class NullHeartbeatSender implements HeartbeatSenderInterface
{
    public function setHeartbeat($timeout)
    {
        return;
    }

    public function shutdown()
    {
        return;
    }
}

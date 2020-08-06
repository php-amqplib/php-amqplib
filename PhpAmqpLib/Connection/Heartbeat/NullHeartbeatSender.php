<?php
namespace PhpAmqpLib\Connection\Heartbeat;

final class NullHeartbeatSender implements HeartbeatSenderInterface
{
    public function signalActivity()
    {
        return;
    }

    public function setHeartbeat($timeout)
    {
        return;
    }

    public function shutdown()
    {
        return;
    }
}

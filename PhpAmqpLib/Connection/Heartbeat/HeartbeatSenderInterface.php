<?php
namespace PhpAmqpLib\Connection\Heartbeat;

interface HeartbeatSenderInterface
{
    public function signalActivity();

    public function setHeartbeat($timeout);

    public function shutdown();
}

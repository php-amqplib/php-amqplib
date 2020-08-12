<?php
namespace PhpAmqpLib\Connection\Heartbeat;

interface HeartbeatSenderInterface
{
    public function register();

    public function unregister();
}

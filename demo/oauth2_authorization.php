<?php

include(__DIR__ . '/config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;


require_once __DIR__ . '/../tests/generate_jwt_keys.php';

// you should implement your own logic to provide tokens to authorize against RabbitMQ broker from your OAuth server
function getNextOauth2Token() {
    static $tokens = [
        JWT_TOKEN_1, // connect using this token
        JWT_TOKEN_2, // upgrade on first refresh
    ];

    if (empty($tokens)) {
        return "invalidToken"; // fail on 2nd refresh
    }

    return array_shift($tokens);
}

$connection = new AMQPStreamConnection(HOST, PORT, posix_getpid(), getNextOauth2Token(), VHOST);

// for workers running longer than the token expiration time, YOU HAVE TO REFRESH TOKEN proactively
// we will use pcntl alarm for this purpose - we are refreshing every 5 seconds

pcntl_async_signals(true);
pcntl_signal(SIGALRM, function () use ($connection) {
    echo "Refreshing token...\n";
    $connection->updatePassword(getNextOauth2Token()); // this will fail on 2nd attempt - see getNextOauth2Token
    pcntl_alarm(5);
}, true);
pcntl_alarm(5);

register_shutdown_function(function () use ($connection) {
    $connection->close();
});

while (true) {
    echo "Connection is ", ($connection->isConnected() ? "connected" : "not connected"), "\n";
    sleep(1);
}

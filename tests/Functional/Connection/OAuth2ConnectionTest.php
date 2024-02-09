<?php

namespace Functional\Connection;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;

/**
 * @group connection
 */
class OAuth2ConnectionTest extends AbstractConnectionTest
{
    /**
     * @test
     * @covers \PhpAmqpLib\Connection\AbstractConnection::connection_update_secret_ok()
     * @covers \PhpAmqpLib\Connection\AbstractConnection::x_update_secret()
     * @covers \PhpAmqpLib\Connection\AbstractConnection::updatePassword()
     */
    public function update_password()
    {
        $conn = new AMQPStreamConnection(HOST, PORT, 'oauth', JWT_TOKEN_1, '/', false, 'PLAIN', null, 'en_US', 1);
        $conn->updatePassword(JWT_TOKEN_2);
        self::assertTrue($conn->isConnected());
    }

    /**
     * @test
     * @covers \PhpAmqpLib\Connection\AbstractConnection::x_update_secret()
     * @covers \PhpAmqpLib\Connection\AbstractConnection::updatePassword()
     */
    public function update_password_invalid()
    {
        $this->expectException(AMQPConnectionClosedException::class);
        $this->expectExceptionMessage('New secret was refused');

        $conn = new AMQPStreamConnection(HOST, PORT, 'oauth', JWT_TOKEN_1, '/', false, 'PLAIN', null, 'en_US', 1);
        $conn->updatePassword('invalidJwt');
    }
}

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
     * @covers \PhpAmqpLib\Connection\AbstractConnection::updatePassword()
     */
    public function update_password_should_replace_password_for_reconnect()
    {
        $conn = new AMQPStreamConnection(HOST, PORT, 'oauth', JWT_TOKEN_1, '/', false, 'PLAIN', null, 'en_US', 1);
        $conn->updatePassword(JWT_TOKEN_2);

        $loginResponse = (new \ReflectionClass($conn))->getProperty('login_response');
        $loginResponse->setAccessible(true);

        self::assertStringContainsString(JWT_TOKEN_2, $loginResponse->getValue($conn));
        self::assertStringNotContainsString(JWT_TOKEN_1, $loginResponse->getValue($conn));
    }

    /**
     * @test
     * @covers \PhpAmqpLib\Connection\AbstractConnection::updatePassword()
     * @covers \PhpAmqpLib\Connection\AbstractConnection::replace_password_in_construct_params()
     * @covers \PhpAmqpLib\Connection\AMQPStreamConnection::replace_password_in_construct_params()
     */
    public function update_password_should_replace_password_for_clone()
    {
        $conn = new AMQPStreamConnection(HOST, PORT, 'oauth', JWT_TOKEN_1, '/', false, 'PLAIN', null, 'en_US', 1);
        $conn->updatePassword(JWT_TOKEN_2);

        $constructorParams = (new \ReflectionClass($conn))->getProperty('construct_params');
        $constructorParams->setAccessible(true);

        self::assertContains(JWT_TOKEN_2, $constructorParams->getValue($conn));
        self::assertNotContains(JWT_TOKEN_1, $constructorParams->getValue($conn));
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

<?php

namespace PhpAmqpLib\Tests\Functional\Connection;

use Httpful\Exception\ConnectionErrorException;
use Httpful\Request;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;

class ConnectionAuthTest extends AbstractConnectionTest
{
    /**
     * @test
     * @group connection
     * @group management
     * @covers \PhpAmqpLib\Connection\AbstractConnection::__construct()
     */
    public function plain_auth_passwordless_must_fail()
    {
        $username = 'test_' . rand();
        // Setting password_hash to "" will ensure the user cannot use a password to log in
        $this->createUser($username, '');

        try {
            $connection = new AMQPStreamConnection(HOST, PORT, $username, '', '/', false, 'PLAIN', null, 'en_US', 1);
            $this->assertInstanceOf(AMQPStreamConnection::class, $connection);
            $this->assertTrue($connection->isConnected());
        } catch (AMQPExceptionInterface $exception) {
            // rabbitmq does not respond to wrong auth content due to empty password
            $this->assertInstanceOf(AMQPTimeoutException::class, $exception);
        } finally {
            $this->deleteUser($username);
        }

        $this->createUser($username, $password = 'password');

        try {
            $connection = new AMQPStreamConnection(
                HOST,
                PORT,
                $username,
                $password,
                '/',
                false,
                'PLAIN',
                null,
                'en_US',
                1
            );
            $this->assertInstanceOf(AMQPStreamConnection::class, $connection);
            $this->assertTrue($connection->isConnected());
            $channel = $connection->channel();
            $this->assertInstanceOf(AMQPChannel::class, $channel);
            $connection->close();
        } finally {
            $this->deleteUser($username);
        }
    }

    private function createUser($username, $password)
    {
        $userEndpoint = $this->getManagementBase() . 'api/users/' . $username;
        $passwordHash = '';
        if (!empty($password)) {
            $salt = substr(md5(mt_rand()), 0, 4);
            $passwordHash = base64_encode($salt . hash('sha256', $salt . $password, true));
        }
        $request = Request::put(
            $userEndpoint,
            json_encode([
                'password_hash' => $passwordHash,
                'tags' => '',
                'hashing_algorithm' => 'rabbit_password_hashing_sha256',
            ])
        );
        $request->expectsJson();
        $request->basicAuth(USER, PASS);
        $request->whenError(function ($error) {
        });
        try {
            $response = $request->send();
        } catch (ConnectionErrorException $exception) {
            $this->markTestSkipped($exception->getMessage());
        }
        if ($response->code !== 201) {
            $this->markTestSkipped('Cannot create temporary user');
        }

        $request = Request::put(
            $this->getManagementBase() . 'api/permissions/%2f/' . $username,
            json_encode(['configure' => '', 'write' => '.*', 'read' => '.*'])
        );
        $request->expectsJson();
        $request->basicAuth(USER, PASS);
        $response = $request->send();
        if ($response->code !== 201) {
            $this->markTestSkipped('Cannot set vhost permission');
        }
    }

    private function deleteUser($username)
    {
        $userEndpoint = $this->getManagementBase() . 'api/users/' . $username;
        $request = Request::delete($userEndpoint);
        $request->expectsJson();
        $request->basicAuth(USER, PASS);
        $request->send();
    }

    private function getManagementBase()
    {
        return 'https://' . HOST . ':15671/';
    }
}

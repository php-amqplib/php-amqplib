<?php

namespace PhpAmqpLib\Tests\Unit\Connection;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exception\AMQPIOException;
use PHPUnit\Framework\TestCase;

class AMQPConnectionConfigTest extends TestCase
{
    /**
     * @test
     */
    public function check_default_connection_name()
    {
        $config = new AMQPConnectionConfig();
        $this->assertEquals('', $config->getConnectionName());
    }

    /**
     * @test
     */
    public function set_get_connection_name()
    {
        $config = new AMQPConnectionConfig();
        $name = 'Connection_01';
        $config->setConnectionName($name);
        $this->assertEquals($name, $config->getConnectionName());
    }

    /**
     * @test
     */
    public function external_auth_with_user_credentials()
    {
        $this->expectException(\InvalidArgumentException::class);
        $config = new AMQPConnectionConfig();
        $config->setUser('');
        $config->setPassword('');
        $config->setLoginMethod(AMQPConnectionConfig::AUTH_EXTERNAL);

        $config = new AMQPConnectionConfig();
        $config->setLoginMethod(AMQPConnectionConfig::AUTH_EXTERNAL);
    }

    /**
     * @test
     */
    public function secure_with_incorrect_crypto_method()
    {
        $this->expectException(AMQPIOException::class);

        $cert_dir = realpath(__DIR__ . "/../../certs");
        $config = new AMQPConnectionConfig();
        $config->setHost(HOST);
        $config->setPort(5671);
        $config->setUser(USER);
        $config->setPassword(PASS);
        $config->setVhost(VHOST);

        $config->setIsSecure(true);
        $config->setSslCryptoMethod(STREAM_CRYPTO_METHOD_ANY_SERVER);

        $config->setSslVerify(true);
        // CommonName is different make sure to not check
        $config->setSslVerifyName(false);

        $config->setSslCaCert($cert_dir . "/ca_certificate.pem");
        $config->setSslKey($cert_dir . "/client_key.pem");
        $config->setSslCert($cert_dir . "/client_certificate.pem");

        AMQPConnectionFactory::create($config);
    }

    /**
     * @test
     */
    public function secure_with_correct_crypto_method()
    {
        $cert_dir = realpath(__DIR__ . "/../../certs");
        $config = new AMQPConnectionConfig();
        $config->setHost(HOST);
        $config->setPort(5671);
        $config->setUser(USER);
        $config->setPassword(PASS);
        $config->setVhost(VHOST);

        $config->setSslCaCert($cert_dir . "/ca_certificate.pem");
        $config->setSslKey($cert_dir . "/client_key.pem");
        $config->setSslCert($cert_dir . "/client_certificate.pem");

        // setIsSecure now also set correct crypto method to tls
        $config->setIsSecure(true);
        $config->setSslVerify(true);

        // CommonName is different make sure to not check
        $config->setSslVerifyName(false);

        $connection = AMQPConnectionFactory::create($config);

        $this->assertEquals(true, $connection->isConnected());
    }

    /**
     * @test
     */
    public function insecure_connection()
    {
        $config = new AMQPConnectionConfig();
        $config->setHost(HOST);
        $config->setPort(5671);
        $config->setUser(USER);
        $config->setPassword(PASS);
        $config->setVhost(VHOST);

        $config->setIsSecure(true);

        // This will set sslverifyname to false
        $config->setSslVerify(false);

        $connection = AMQPConnectionFactory::create($config);

        $this->assertEquals(true, $connection->isConnected());
    }

    /**
     * @test
     */
    public function check_invalid_port_number()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Port number must be greater than 0');

        $config = new AMQPConnectionConfig();
        $config->setPort(-1);
    }

    /**
     * @test
     */
    public function check_invalid_login_method()
    {
        $this->expectException(\InvalidArgumentException::class);

        $config = new AMQPConnectionConfig();
        $config->setLoginMethod('INVALID_METHOD');
    }

    /**
     * @test
     */
    public function set_invalid_amqp_protocol()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP protocol can be either "0.9.1" or "8.0"');

        $config = new AMQPConnectionConfig();
        $protocol = "invalid_protocol";
        $config->setAMQPProtocol($protocol);
    }

    /**
     * @test
     */
    public function set_invalid_stream_context()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Resource must be valid stream context');

        $config = new AMQPConnectionConfig();
        $invalidResource = tmpfile();
        $config->setStreamContext($invalidResource);
    }
}

<?php

namespace PhpAmqpLib\Tests\Unit\Connection;

use InvalidArgumentException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AMQPStreamConnectionTest extends TestCase
{
    /**
     * @test
     */
    public function channel_rpc_timeout_should_be_invalid_if_greater_than_read_write_timeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('channel RPC timeout must not be greater than I/O read-write timeout');

        new AMQPStreamConnection(
            HOST,
            PORT,
            USER,
            PASS,
            VHOST,
            false,
            'AMQPLAIN',
            null,
            'en_US',
            3.0,
            3.0,
            null,
            false,
            0,
            5.0
        );
    }

    /**
     * @test
     */
    public function trigger_deprecation_is_ssl_protocl_set(): void
    {
        set_error_handler(
            static function ($errno, $errstr) {
                restore_error_handler();
                throw new RuntimeException($errstr, $errno);
            },
            E_USER_DEPRECATED
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            '$ssl_protocol parameter is deprecated, use stream_context_set_option($context, \'ssl\', \'crypto_method\', $ssl_protocol) instead (see https://www.php.net/manual/en/function.stream-socket-enable-crypto.php for possible values)'
        );

        new AMQPStreamConnection(
            HOST,
            PORT,
            USER,
            PASS,
            VHOST,
            false,
            'AMQPLAIN',
            null,
            'en_US',
            3.0,
            3.0,
            null,
            false,
            0,
            3.0,
            'test_ssl_protocol'
        );
    }

    /**
     * @test
     */
    public function trigger_deprecation_is_ssl_protocl_set_with_named_params(): void
    {
        if (PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('Named parameters are available in PHP 8.0+');
        }

        set_error_handler(
            static function ($errno, $errstr) {
                restore_error_handler();
                throw new RuntimeException($errstr, $errno);
            },
            E_USER_DEPRECATED
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            '$ssl_protocol parameter is deprecated, use stream_context_set_option($context, \'ssl\', \'crypto_method\', $ssl_protocol) instead (see https://www.php.net/manual/en/function.stream-socket-enable-crypto.php for possible values)'
        );

        new AMQPStreamConnection(
            host: HOST,
            port: PORT,
            user: USER,
            password: PASS,
            vhost: VHOST,
            channel_rpc_timeout: 3.0,
            ssl_protocol: 'test_ssl_protocol'
        );
    }
}

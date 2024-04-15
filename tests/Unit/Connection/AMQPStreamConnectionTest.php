<?php

namespace PhpAmqpLib\Tests\Unit\Connection;

use InvalidArgumentException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\TestCase;

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
     * Generate deprecation warning if ssl_protocol is set
     */
    public function trigger_deprecation_is_ssl_protocol_set(): void
    {
        $deprecationMessage = '';
        $deprecationCode = '';
        set_error_handler(
            static function ($errno, $errstr) use (&$deprecationMessage, &$deprecationCode) {
                $deprecationMessage = $errstr;
                $deprecationCode = $errno;
            },
            E_USER_DEPRECATED
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

        restore_error_handler();
        $this->assertEquals(E_USER_DEPRECATED, $deprecationCode);
        $this->assertEquals('$ssl_protocol parameter is deprecated, use stream_context_set_option($context, \'ssl\', \'crypto_method\', $ssl_protocol) instead (see https://www.php.net/manual/en/function.stream-socket-enable-crypto.php for possible values)', $deprecationMessage);
    }
}

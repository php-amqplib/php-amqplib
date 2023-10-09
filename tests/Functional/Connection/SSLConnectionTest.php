<?php

namespace PhpAmqpLib\Tests\Functional\Connection;

use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;

/**
 * @group connection
 * @requires OS Linux|Darwin
 */
class SSLConnectionTest extends AbstractConnectionTest
{
    /**
     * @test
     * @dataProvider secure_connection_params
     */
    public function secure_connection_default_params($options)
    {
        $port = $options['port'] ?? 5671;
        $connection = $this->connection_create('ssl', HOST, $port, $options);
        self::assertTrue($connection->isConnected());
        $channel = $connection->channel();
        self::assertTrue($channel->is_open());

        $channel->close();
        $connection->close();
    }

    /**
     * @test
     * @dataProvider secure_connection_params
     */
    public function secure_connection_default_params_with_keepalive($options)
    {
        $options['keepalive'] = true;
        $this->secure_connection_default_params($options);
    }

    public function secure_connection_params()
    {
        $sets = [];

        $certsPath = realpath(__DIR__ . '/../../certs');

        // #0 peer verification
        $options = [
            'ssl' => [
                'cafile' => $certsPath . '/ca_certificate.pem',
                'local_cert' => $certsPath . '/client_certificate.pem',
                'local_pk' => $certsPath . '/client_key.pem',
                'verify_peer' => true,
                'verify_peer_name' => false,
            ],
        ];
        $sets[] = [
            $options
        ];

        // #1 TLS protocol
        $options['protocol'] = 'tlsv1.2';
        $sets[] = [$options];

        // #2 SNI_enabled
        $options = [
            'ssl' => [
                'cafile' => $certsPath . 'ca_certificate.pem',
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
            ]
        ];
        $sets[] = [$options];

        // #3 capath option
        $options = [
            'ssl' => [
                'capath' => $certsPath . '/hashed/',
                'verify_peer_name' => false,
            ],
        ];
        $sets[] = [
            $options
        ];

        // #4 non-TLS options
        $options = ['port' => 5672];
        $sets[] = [
            $options
        ];

        // #5 TLS crypto method
        $options[] = ['ssl' => ['crypto_method' => STREAM_CRYPTO_METHOD_ANY_CLIENT]];
        $sets[] = [
            $options
        ];

        return $sets;
    }
}

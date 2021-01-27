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
        $connection = $this->conection_create('ssl', HOST, 5671, $options);
        self::assertTrue($connection->isConnected());
        $channel = $connection->channel();
        self::assertTrue($channel->is_open());

        $channel->close();
        $connection->close();
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

        return $sets;
    }
}

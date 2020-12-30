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
     * @dataProvider secureConnectionParams
     */
    public function secureConnectionDefaultParams($options)
    {
        $connection = $this->conectionCreate('ssl', HOST, 5671, $options);
        self::assertTrue($connection->isConnected());
        $channel = $connection->channel();
        self::assertTrue($channel->isOpen());

        $channel->close();
        $connection->close();
    }

    public function secureConnectionParams()
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
        $options['protocol'] = 'tls';
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

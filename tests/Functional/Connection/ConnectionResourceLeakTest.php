<?php

namespace Functional\Connection;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;

/**
 * @group connection
 */
class ConnectionResourceLeakTest extends AbstractConnectionTest
{
    /**
     * @test
     */
    public function too_many_resources_after_close()
    {
        $max = 2000;
        $connections = [];
        $previousNumberOfResources = $this->getResourcesCount();

        foreach (range(1, $max) as $i) {
            /** @var AbstractConnection $connection */
            $connection = $this->connection_create('stream', HOST, PORT, ['lazy' => true]);
            $connection->close();
            $connections[] = $connection;
        }
        // resources from other tests might not be closed yet
        self::assertLessThanOrEqual($previousNumberOfResources, $this->getResourcesCount());
    }

    private function getResourcesCount(): int
    {
        return count(get_resources('stream-context'));
    }
}

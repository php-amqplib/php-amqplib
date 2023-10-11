<?php

namespace PhpAmqpLib\Wire;

use PhpAmqpLib\Exception\AMQPDataReadException;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPNoDataException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Helper\MiscHelper;
use PhpAmqpLib\Wire\IO\AbstractIO;
use RuntimeException;

class AMQPIOReader extends AMQPReader
{
    /** @var AbstractIO */
    private $io;

    /** @var int|float|null */
    protected $timeout;

    public function __construct(AbstractIO $io, $timeout = 0)
    {
        $this->io = $io;
        $this->timeout = $timeout;
    }

    public function close(): void
    {
        $this->io->close();
    }

    /**
     * @return float|int|mixed|null
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Sets the timeout (second)
     *
     * @param int|float|null $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * @param int $n
     * @return string
     * @throws RuntimeException
     * @throws AMQPDataReadException|AMQPNoDataException|AMQPIOException
     */
    protected function rawread(int $n): string
    {
        $res = '';
        while (true) {
            $this->wait();
            try {
                $res = $this->io->read($n);
                break;
            } catch (AMQPTimeoutException $e) {
                if ($this->getTimeout() > 0) {
                    throw $e;
                }
            }
        }
        $this->offset += $n;

        return $res;
    }

    /**
     * Waits until some data is retrieved from the socket.
     *
     * AMQPTimeoutException can be raised if the timeout is set
     *
     * @throws AMQPTimeoutException when timeout is set and no data received
     * @throws AMQPNoDataException when no data is ready to read from IO
     */
    protected function wait(): void
    {
        $timeout = $this->timeout;
        if (null === $timeout) {
            // timeout=null just poll state and return instantly
            $result = $this->io->select(0);
            if ($result === 0) {
                throw new AMQPNoDataException('No data is ready to read');
            }
            return;
        }

        if (!($timeout > 0)) {
            // wait indefinitely for data if timeout=0
            $result = $this->io->select(null);
            if ($result === 0) {
                throw new AMQPNoDataException('No data is ready to read');
            }
            return;
        }

        $leftTime = $timeout;
        $started = microtime(true);
        do {
            [$sec, $usec] = MiscHelper::splitSecondsMicroseconds($leftTime);
            $result = $this->io->select($sec, $usec);
            if ($result > 0) {
                return;
            }
            // select might be interrupted by signal, calculate left time and repeat
            $leftTime = $timeout - (microtime(true) - $started);
        } while ($leftTime > 0);

        throw new AMQPTimeoutException(sprintf(
                                           'The connection timed out after %s sec while awaiting incoming data',
                                           $timeout
                                       ));

    }
}

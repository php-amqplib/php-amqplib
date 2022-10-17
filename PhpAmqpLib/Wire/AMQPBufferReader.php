<?php

namespace PhpAmqpLib\Wire;

use PhpAmqpLib\Exception\AMQPDataReadException;

class AMQPBufferReader extends AMQPReader
{
    /**
     * @var string
     */
    private $buffer;

    /**
     * @var int
     */
    private $length;

    public function __construct(string $buffer)
    {
        $this->buffer = $buffer;
        $this->length = mb_strlen($buffer, 'ASCII');
    }

    public function close(): void
    {
    }

    /**
     * Resets the object from the injected param
     *
     * Used to not need to create a new AMQPBufferReader instance every time.
     * when we can just pass a string and reset the object state.
     * NOTE: since we are working with strings we don't need to pass an AbstractIO
     *       or a timeout.
     *
     * @param string $str
     */
    public function reset(string $str): void
    {
        $this->buffer = $str;
        $this->length = mb_strlen($this->buffer, 'ASCII');
        $this->offset = 0;
        $this->resetCounters();
    }

    protected function rawread(int $n): string
    {
        if ($this->length < $n) {
            throw new AMQPDataReadException(sprintf(
                                                'Error reading data. Requested %s bytes while string buffer has only %s',
                                                $n,
                                                $this->length
                                            ));
        }

        $res = mb_substr($this->buffer, 0, $n, 'ASCII');
        $this->buffer = mb_substr($this->buffer, $n, null, 'ASCII');
        $this->length -= $n;
        $this->offset += $n;

        return $res;
    }

}

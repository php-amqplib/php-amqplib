<?php

namespace PhpAmqpLib\Tests\Unit\Test;

use PhpAmqpLib\Wire\IO\AbstractIO;

class BufferIO extends AbstractIO
{
    private $buffer;

    /**
     * @inheritDoc
     */
    public function read($len)
    {
        return fread($this->buffer, $len);
    }

    /**
     * @inheritDoc
     */
    public function write($data)
    {
        fwrite($this->buffer, $data);
    }

    /**
     * @inheritDoc
     */
    public function close()
    {
        fclose($this->buffer);
        $this->buffer = null;
    }

    /**
     * @inheritDoc
     */
    protected function do_select(?int $sec, int $usec)
    {
        return !feof($this->buffer);
    }

    /**
     * @inheritDoc
     */
    public function connect()
    {
        $this->buffer = fopen('php://memory', 'rb+');
    }

    /**
     * @inheritDoc
     */
    public function getSocket()
    {
        return $this->buffer;
    }
}

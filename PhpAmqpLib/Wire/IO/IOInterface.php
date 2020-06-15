<?php

namespace PhpAmqpLib\Wire\IO;

/**
 * IOInterface Interface
 */
interface IOInterface
{
    /**
     * Read the io
     *
     * @param int $len
     * @return string
     */
    public function read($len);

    /**
     * Write into the io
     *
     * @param string $data
     */
    public function write($data);

    /**
     * Close the io
     *
     * @return void
     */
    public function close();

    /**
     * Select method
     *
     * @param int|null $sec
     * @param int|null $usec
     * @return int
     */
    public function select($sec, $usec);

    /**
     * Heartbeat logic: check connection health here
     *
     * @return void
     */
    public function check_heartbeat();

    /**
     * Disable the heartbeat check
     *
     * @return self
     */
    public function disableHeartbeat();

    /**
     * Reenable the heartbeat check
     *
     * @return self
     */
    public function reenableHeartbeat();

    /**
     * Internal error handler to deal with stream and socket errors.
     *
     * @param  int $errno
     * @param  string $errstr
     * @param  string $errfile
     * @param  int $errline
     * @param  array $errcontext
     * @return void
     */
    public function error_handler($errno, $errstr, $errfile, $errline, $errcontext = null);
}

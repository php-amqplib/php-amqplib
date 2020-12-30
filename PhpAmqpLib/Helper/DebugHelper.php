<?php

namespace PhpAmqpLib\Helper;

use PhpAmqpLib\Wire\Constants;

class DebugHelper
{
    /**
     * @var bool
     */
    protected $debug;

    /**
     * @var resource
     */
    protected $debugOutput;

    /**
     * @var Constants
     */
    protected $constants;

    /**
     * @param Constants $constants
     */
    public function __construct(Constants $constants)
    {
        $this->debug = defined('AMQP_DEBUG') ? AMQP_DEBUG : false;
        if (defined('AMQP_DEBUG_OUTPUT')) {
            $this->debugOutput = AMQP_DEBUG_OUTPUT;
        } else {
            $this->debugOutput = fopen('php://output', 'wb');
        }
        $this->constants = $constants;
    }

    /**
     * @param string $msg
     */
    public function debugMsg($msg)
    {
        if ($this->debug) {
            $this->printMsg($msg);
        }
    }

    /**
     * @param array $allowedMethods
     */
    public function debugAllowedMethods($allowedMethods)
    {
        if ($this->debug) {
            if ($allowedMethods) {
                $msg = 'waiting for ' . implode(', ', $allowedMethods);
            } else {
                $msg = 'waiting for any method';
            }
            $this->debugMsg($msg);
        }
    }

    /**
     * @param string $methodSig
     */
    public function debugMethodSignature1($methodSig)
    {
        $this->debugMethodSignature('< %s:', $methodSig);
    }

    /**
     * @param string $msg
     * @param string $methodSig
     */
    public function debugMethodSignature($msg, $methodSig)
    {
        if ($this->debug) {
            $constants = $this->constants;
            $methods = $constants::$GLOBAL_METHOD_NAMES;
            $key = MiscHelper::methodSig($methodSig);
            $this->debugMsg(sprintf($msg . ': %s', $key, $methods[$key]));
        }
    }

    /**
     * @param string $data
     */
    public function debugHexdump($data)
    {
        if ($this->debug) {
            $this->debugMsg(
                sprintf(
                    '< [hex]: %s%s',
                    PHP_EOL,
                    MiscHelper::hexdump($data, $htmloutput = false, $uppercase = true, $return = true)
                )
            );
        }
    }

    /**
     * @param int $versionMajor
     * @param int $versionMinor
     * @param array $serverProperties
     * @param array $mechanisms
     * @param array $locales
     */
    public function debugConnectionStart($versionMajor, $versionMinor, $serverProperties, $mechanisms, $locales)
    {
        if ($this->debug) {
            $this->debugMsg(
                sprintf(
                    'Start from server, version: %d.%d, properties: %s, mechanisms: %s, locales: %s',
                    $versionMajor,
                    $versionMinor,
                    MiscHelper::dumpTable($serverProperties),
                    implode(', ', $mechanisms),
                    implode(', ', $locales)
                )
            );
        }
    }

    /**
     * @param string $s
     */
    protected function printMsg($s)
    {
        fwrite($this->debugOutput, $s . PHP_EOL);
    }
}

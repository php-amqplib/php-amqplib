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
    protected $debug_output;

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
            $this->debug_output = AMQP_DEBUG_OUTPUT;
        } else {
            $this->debug_output = fopen('php://output', 'wb');
        }
        $this->constants = $constants;
    }

    /**
     * @param string $msg
     */
    public function debug_msg($msg)
    {
        if ($this->debug) {
            $this->print_msg($msg);
        }
    }

    /**
     * @param array|null $allowed_methods
     */
    public function debug_allowed_methods($allowed_methods)
    {
        if ($this->debug) {
            if ($allowed_methods) {
                $msg = 'waiting for ' . implode(', ', $allowed_methods);
            } else {
                $msg = 'waiting for any method';
            }
            $this->debug_msg($msg);
        }
    }

    /**
     * @param string|array $method_sig
     */
    public function debug_method_signature1($method_sig)
    {
        $this->debug_method_signature('< %s:', $method_sig);
    }

    /**
     * @param string $msg
     * @param string|array $method_sig
     */
    public function debug_method_signature($msg, $method_sig)
    {
        if ($this->debug) {
            $constants = $this->constants;
            $methods = $constants::$GLOBAL_METHOD_NAMES;
            $key = MiscHelper::methodSig($method_sig);
            $this->debug_msg(sprintf($msg . ': %s', $key, $methods[$key]));
        }
    }

    /**
     * @param string $data
     */
    public function debug_hexdump($data)
    {
        if ($this->debug) {
            $this->debug_msg(
                sprintf(
                    '< [hex]: %s%s',
                    PHP_EOL,
                    MiscHelper::hexdump($data, $htmloutput = false, $uppercase = true, $return = true)
                )
            );
        }
    }

    /**
     * @param int $version_major
     * @param int $version_minor
     * @param array $server_properties
     * @param array $mechanisms
     * @param array $locales
     */
    public function debug_connection_start($version_major, $version_minor, $server_properties, $mechanisms, $locales)
    {
        if ($this->debug) {
            $this->debug_msg(
                sprintf(
                    'Start from server, version: %d.%d, properties: %s, mechanisms: %s, locales: %s',
                    $version_major,
                    $version_minor,
                    MiscHelper::dump_table($server_properties),
                    implode(', ', $mechanisms),
                    implode(', ', $locales)
                )
            );
        }
    }

    /**
     * @param string $s
     */
    protected function print_msg($s)
    {
        fwrite($this->debug_output, $s . PHP_EOL);
    }
}

<?php
namespace PhpAmqpLib\Helper;

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
     * @var string
     */
    protected $PROTOCOL_CONSTANTS_CLASS;

    /**
     * @param string $PROTOCOL_CONSTANTS_CLASS
     */
    public function __construct($PROTOCOL_CONSTANTS_CLASS) {
        if(!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'w'));

        $this->debug = defined('AMQP_DEBUG') ? AMQP_DEBUG : false;
        $this->debug_output = defined('AMQP_DEBUG_OUTPUT') ? AMQP_DEBUG_OUTPUT : STDOUT;
        $this->PROTOCOL_CONSTANTS_CLASS = $PROTOCOL_CONSTANTS_CLASS;
    }

    /**
     * @param string $msg
     */
    public function debug_msg($msg) {
        if ($this->debug) {
            $this->print_msg($msg);
        }
    }

    /**
     * @param array $allowed_methods
     */
    public function debug_allowed_methods($allowed_methods) {
        if ($allowed_methods) {
            $msg = 'waiting for ' . implode(', ', $allowed_methods);
        } else {
            $msg = 'waiting for any method';
        }
        $this->debug_msg($msg);
    }

    /**
     * @param string $method_sig
     */
    public function debug_method_signature1($method_sig) {
        $this->debug_method_signature('< %s:', $method_sig);
    }

    /**
     * @param string $msg
     * @param string $method_sig
     */
    public function debug_method_signature($msg, $method_sig) {
        if ($this->debug) {
            $protocolClass = $this->PROTOCOL_CONSTANTS_CLASS;
            $this->debug_msg(sprintf(
                $msg . ': %s',
                MiscHelper::methodSig($method_sig),
                $protocolClass::$GLOBAL_METHOD_NAMES[MiscHelper::methodSig($method_sig)]
            ));
        }
    }

    /**
     * @param string $data
     */
    public function debug_hexdump($data) {
        if ($this->debug) {
            $this->debug_msg(sprintf(
                '< [hex]: %s%s',
                PHP_EOL,
                MiscHelper::hexdump($data, $htmloutput = false, $uppercase = true, $return = true)
            ));
        }
    }

    /**
     * @param int $version_major
     * @param int $version_minor
     * @param array $server_properties
     * @param array $mechanisms
     * @param array $locales
     */
    public function debug_connection_start($version_major, $version_minor, $server_properties, $mechanisms, $locales) {
        if ($this->debug) {
            $this->debug_msg(sprintf(
                'Start from server, version: %d.%d, properties: %s, mechanisms: %s, locales: %s',
                $version_major,
                $version_minor,
                MiscHelper::dump_table($server_properties),
                implode(', ', $mechanisms),
                implode(', ', $locales)
            ));
        }
    }

    /**
     * @param string $s
     */
    protected function print_msg($s) {
        fwrite($this->debug_output, $s . PHP_EOL);
    }
}

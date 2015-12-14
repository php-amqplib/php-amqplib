<?php
namespace PhpAmqpLib\Helper;

class DebugHelper
{
    protected $debug;

    protected $PROTOCOL_CONSTANTS_CLASS;

    public function __construct($PROTOCOL_CONSTANTS_CLASS) {
        $this->debug = defined('AMQP_DEBUG') ? AMQP_DEBUG : false;
        $this->PROTOCOL_CONSTANTS_CLASS = $PROTOCOL_CONSTANTS_CLASS;
    }

    public function debug_msg($msg) {
        if ($this->debug) {
            $this->print_msg($msg);
        }
    }

    public function debug_allowed_methods($allowed_methods) {
        if ($allowed_methods) {
            $msg = 'waiting for ' . implode(', ', $allowed_methods);
        } else {
            $msg = 'waiting for any method';
        }
        $this->debug_msg($msg);
    }

    public function debug_method_signature1($method_sig) {
        $this->debug_method_signature('< %s:', $method_sig);
    }

    public function debug_method_signature($msg, $method_sig) {
        if ($this->debug) {
            $PROTOCOL_CONSTANTS_CLASS = $this->PROTOCOL_CONSTANTS_CLASS;
            $this->debug_msg(sprintf(
                    $msg . ': %s',
                    MiscHelper::methodSig($method_sig),
                    $PROTOCOL_CONSTANTS_CLASS::$GLOBAL_METHOD_NAMES[MiscHelper::methodSig($method_sig)]
                ));
        }
    }

    public function debug_hexdump($data) {
        if ($this->debug) {
            $this->debug_msg(sprintf(
                    '< [hex]: %s%s',
                    PHP_EOL,
                    MiscHelper::hexdump($data, $htmloutput = false, $uppercase = true, $return = true)
                ));
        }
    }

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

    protected function print_msg($s) {
        echo $s . PHP_EOL;
    }
}
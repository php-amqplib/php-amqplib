<?php

namespace PhpAmqpLib\Wire;

use phpseclib\Math\BigInteger;

class AbstractClient
{
    /**
     * @var bool
     */
    protected $is64bits;

    /** @var BigInteger[][] */
    protected static $bigIntegers = array();

    /**
     * @var bool
     */
    protected static $isLittleEndian;

    public function __construct()
    {
        $this->is64bits = PHP_INT_SIZE === 8;
    }

    /**
     * Converts byte-string between native and network byte order, in both directions
     *
     * @param string $bytes
     * @return string
     */
    protected function correctEndianness($bytes)
    {
        return self::isLittleEndian() ? $this->convertByteOrder($bytes) : $bytes;
    }

    /**
     * @param string $bytes
     * @return string
     */
    protected function convertByteOrder($bytes)
    {
        return strrev($bytes);
    }

    /**
     * @param int $longInt
     * @return bool
     */
    protected function getLongMSB($longInt)
    {
        return (bool) ($longInt & 0x80000000);
    }

    /**
     * @param string $bytes
     * @return bool
     */
    protected function getMSB($bytes)
    {
        return ord($bytes[0]) > 127;
    }

    /**
     * @return bool
     */
    protected static function isLittleEndian()
    {
        if (self::$isLittleEndian === null) {
            $tmp = unpack('S', "\x01\x00"); // to maintain 5.3 compatibility
            self::$isLittleEndian = $tmp[1] === 1;
        }

        return self::$isLittleEndian;
    }

    /**
     * @param string $value
     * @param int $base
     * @return BigInteger
     */
    protected static function getBigInteger($value, $base = 10)
    {
        if (!isset(self::$bigIntegers[$base])) {
            self::$bigIntegers[$base] = array();
        }
        if (isset(self::$bigIntegers[$base][$value])) {
            return self::$bigIntegers[$base][$value];
        }

        $integer = new BigInteger($value, $base);
        self::$bigIntegers[$base][$value] = $integer;

        return $integer;
    }
}

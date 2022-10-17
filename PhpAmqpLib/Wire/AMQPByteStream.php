<?php

namespace PhpAmqpLib\Wire;

use PhpAmqpLib\Helper\BigInteger;

abstract class AMQPByteStream
{
    public const BIT = 1;
    public const OCTET = 1;
    public const SHORTSTR = 1;
    public const SHORT = 2;
    public const LONG = 4;
    public const SIGNED_LONG = 4;
    public const READ_PHP_INT = 4; // use READ_ to avoid possible clashes with PHP
    public const LONGLONG = 8;
    public const TIMESTAMP = 8;

    /** @var bool */
    protected const PLATFORM_64BIT = PHP_INT_SIZE === 8;

    /** @var BigInteger[][] */
    protected static $bigIntegers = array();

    /**
     * @var bool
     */
    protected static $isLittleEndian;

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

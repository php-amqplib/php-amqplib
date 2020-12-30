<?php

namespace PhpAmqpLib\Wire;

use PhpAmqpLib\Exception\AMQPInvalidArgumentException;
use PhpAmqpLib\Exception\AMQPOutOfRangeException;
use phpseclib\Math\BigInteger;

class AMQPWriter extends AbstractClient
{
    /** @var string */
    protected $out = '';

    /** @var array */
    protected $bits = array();

    /** @var int */
    protected $bitcount = 0;

    private function flushbits()
    {
        if (!empty($this->bits)) {
            $this->out .= implode('', array_map('chr', $this->bits));
            $this->bits = array();
            $this->bitcount = 0;
        }
    }

    /**
     * Get what's been encoded so far.
     *
     * @return string
     */
    public function getvalue()
    {
        /* temporarily needed for compatibility with write_bit unit tests */
        if ($this->bitcount) {
            $this->flushbits();
        }

        return $this->out;
    }

    /**
     * Write a plain PHP string, with no special encoding.
     *
     * @param string $s
     *
     * @return $this
     */
    public function write($s)
    {
        $this->out .= $s;

        return $this;
    }

    /**
     * Write a boolean value.
     * (deprecated, use write_bits instead)
     *
     * @deprecated
     * @param bool $b
     * @return $this
     */
    public function writeBit($b)
    {
        $b = $b ? 1 : 0;
        $shift = $this->bitcount % 8;
        $last = $shift === 0 ? 0 : array_pop($this->bits);
        $last |= ($b << $shift);
        $this->bits[] = $last;
        $this->bitcount++;

        return $this;
    }

    /**
     * Write multiple bits as an octet
     *
     * @param bool[] $bits
     * @return $this
     */
    public function writeBits($bits)
    {
        $value = 0;

        foreach ($bits as $n => $bit) {
            $bit = $bit ? 1 : 0;
            $value |= ($bit << $n);
        }

        $this->out .= chr($value);

        return $this;
    }

    /**
     * Write an integer as an unsigned 8-bit value
     *
     * @param int $n
     * @return $this
     * @throws \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function writeOctet($n)
    {
        if ($n < 0 || $n > 255) {
            throw new AMQPInvalidArgumentException('Octet out of range: ' . $n);
        }

        $this->out .= chr($n);

        return $this;
    }

    /**
     * @param int $n
     * @return $this
     */
    public function writeSignedOctet($n)
    {
        if (($n < -128) || ($n > 127)) {
            throw new AMQPInvalidArgumentException('Signed octet out of range: ' . $n);
        }

        $this->out .= pack('c', $n);

        return $this;
    }

    /**
     * Write an integer as an unsigned 16-bit value
     *
     * @param int $n
     * @return $this
     * @throws \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function writeShort($n)
    {
        if ($n < 0 || $n > 65535) {
            throw new AMQPInvalidArgumentException('Short out of range: ' . $n);
        }

        $this->out .= pack('n', $n);

        return $this;
    }

    /**
     * @param int $n
     * @return $this
     */
    public function writeSignedShort($n)
    {
        if (($n < -32768) || ($n > 32767)) {
            throw new AMQPInvalidArgumentException('Signed short out of range: ' . $n);
        }

        $this->out .= $this->correctEndianness(pack('s', $n));

        return $this;
    }

    /**
     * Write an integer as an unsigned 32-bit value
     *
     * @param int|string $n
     * @return $this
     */
    public function writeLong($n)
    {
        if (($n < 0) || ($n > 4294967295)) {
            throw new AMQPInvalidArgumentException('Long out of range: ' . $n);
        }

        //Numeric strings >PHP_INT_MAX on 32bit are casted to PHP_INT_MAX, damn PHP
        if (!$this->is64bits && is_string($n)) {
            $n = (float) $n;
        }
        $this->out .= pack('N', $n);

        return $this;
    }

    /**
     * @param int $n
     * @return $this
     */
    private function writeSignedLong($n)
    {
        if (($n < -2147483648) || ($n > 2147483647)) {
            throw new AMQPInvalidArgumentException('Signed long out of range: ' . $n);
        }

        //on my 64bit debian this approach is slightly faster than splitIntoQuads()
        $this->out .= $this->correctEndianness(pack('l', $n));

        return $this;
    }

    /**
     * Write a numeric value as an unsigned 64-bit value
     *
     * @param int|string $n
     * @return $this
     * @throws AMQPOutOfRangeException
     */
    public function writeLonglong($n)
    {
        if (is_int($n)) {
            if ($n < 0) {
                throw new AMQPOutOfRangeException('Longlong out of range: ' . $n);
            }

            if ($this->is64bits) {
                $res = pack('J', $n);
                $this->out .= $res;
            } else {
                $this->out .= pack('NN', 0, $n);
            }

            return $this;
        }

        $value = new BigInteger($n);
        if (
            $value->compare(self::getBigInteger('0')) < 0
            || $value->compare(self::getBigInteger('FFFFFFFFFFFFFFFF', 16)) > 0
        ) {
            throw new AMQPInvalidArgumentException('Longlong out of range: ' . $n);
        }

        $value->setPrecision(64);
        $this->out .= $value->toBytes();

        return $this;
    }

    /**
     * @param int|string $n
     * @return $this
     */
    public function writeSignedLonglong($n)
    {
        if (is_int($n)) {
            if ($this->is64bits) {
                // q is for 64-bit signed machine byte order
                $packed = pack('q', $n);
                if (self::isLittleEndian()) {
                    $packed = $this->convertByteOrder($packed);
                }
                $this->out .= $packed;
            } else {
                $hi = $n < 0 ? -1 : 0;
                $lo = $n;
                $this->out .= pack('NN', $hi, $lo);
            }

            return $this;
        }

        $value = new BigInteger($n);
        if (
            $value->compare(self::getBigInteger('-8000000000000000', 16)) < 0
            || $value->compare(self::getBigInteger('7FFFFFFFFFFFFFFF', 16)) > 0
        ) {
            throw new AMQPInvalidArgumentException('Signed longlong out of range: ' . $n);
        }

        $value->setPrecision(64);
        $this->out .= substr($value->toBytes(true), -8);

        return $this;
    }

    /**
     * Write a string up to 255 bytes long after encoding.
     * Assume UTF-8 encoding
     *
     * @param string $s
     * @return $this
     * @throws \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function writeShortstr($s)
    {
        $len = mb_strlen($s, 'ASCII');
        if ($len > 255) {
            throw new AMQPInvalidArgumentException('String too long');
        }

        $this->writeOctet($len);
        $this->out .= $s;

        return $this;
    }

    /**
     * Write a string up to 2**32 bytes long.  Assume UTF-8 encoding
     *
     * @param string $s
     * @return $this
     */
    public function writeLongstr($s)
    {
        $this->writeLong(mb_strlen($s, 'ASCII'));
        $this->out .= $s;

        return $this;
    }

    /**
     * Supports the writing of Array types, so that you can implement
     * array methods, like Rabbitmq's HA parameters
     *
     * @param AMQPArray|array $a Instance of AMQPArray or PHP array WITHOUT format hints (unlike write_table())
     * @return self
     */
    public function writeArray($a)
    {
        if (!($a instanceof AMQPArray)) {
            $a = new AMQPArray($a);
        }
        $data = new self();

        foreach ($a as $v) {
            $data->writeValue($v[0], $v[1]);
        }

        $data = $data->getvalue();
        $this->writeLong(mb_strlen($data, 'ASCII'));
        $this->write($data);

        return $this;
    }

    /**
     * Write unix time_t value as 64 bit timestamp
     *
     * @param int $v
     * @return $this
     */
    public function writeTimestamp($v)
    {
        $this->writeLonglong($v);

        return $this;
    }

    /**
     * Write PHP array, as table. Input array format: keys are strings,
     * values are (type,value) tuples.
     *
     * @param AMQPTable|array $d Instance of AMQPTable or PHP array WITH format hints (unlike write_array())
     * @return $this
     * @throws \PhpAmqpLib\Exception\AMQPInvalidArgumentException
     */
    public function writeTable($d)
    {
        $typeIsSym = !($d instanceof AMQPTable); //purely for back-compat purposes

        $table_data = new AMQPWriter();
        foreach ($d as $k => $va) {
            list($ftype, $v) = $va;
            $table_data->writeShortstr($k);
            $table_data->writeValue($typeIsSym ? AMQPAbstractCollection::getDataTypeForSymbol($ftype) : $ftype, $v);
        }

        $table_data = $table_data->getvalue();
        $this->writeLong(mb_strlen($table_data, 'ASCII'));
        $this->write($table_data);

        return $this;
    }

    /**
     * for compat with method mapping used by AMQPMessage
     *
     * @param AMQPTable|array $d
     * @return $this
     */
    public function writeTableObject($d)
    {
        return $this->writeTable($d);
    }

    /**
     * @param int $type One of AMQPAbstractCollection::T_* constants
     * @param mixed $val
     */
    private function writeValue($type, $val)
    {
        //This will find appropriate symbol for given data type for currently selected protocol
        //Also will raise an exception on unknown type
        $this->write(AMQPAbstractCollection::getSymbolForDataType($type));

        switch ($type) {
            case AMQPAbstractCollection::T_INT_SHORTSHORT:
                $this->writeSignedOctet($val);
                break;
            case AMQPAbstractCollection::T_INT_SHORTSHORT_U:
                $this->writeOctet($val);
                break;
            case AMQPAbstractCollection::T_INT_SHORT:
                $this->writeSignedShort($val);
                break;
            case AMQPAbstractCollection::T_INT_SHORT_U:
                $this->writeShort($val);
                break;
            case AMQPAbstractCollection::T_INT_LONG:
                $this->writeSignedLong($val);
                break;
            case AMQPAbstractCollection::T_INT_LONG_U:
                $this->writeLong($val);
                break;
            case AMQPAbstractCollection::T_INT_LONGLONG:
                $this->writeSignedLonglong($val);
                break;
            case AMQPAbstractCollection::T_INT_LONGLONG_U:
                $this->writeLonglong($val);
                break;
            case AMQPAbstractCollection::T_DECIMAL:
                $this->writeOctet($val->getE());
                $this->writeSignedLong($val->getN());
                break;
            case AMQPAbstractCollection::T_TIMESTAMP:
                $this->writeTimestamp($val);
                break;
            case AMQPAbstractCollection::T_BOOL:
                $this->writeOctet($val ? 1 : 0);
                break;
            case AMQPAbstractCollection::T_STRING_SHORT:
                $this->writeShortstr($val);
                break;
            case AMQPAbstractCollection::T_STRING_LONG:
                $this->writeLongstr($val);
                break;
            case AMQPAbstractCollection::T_ARRAY:
                $this->writeArray($val);
                break;
            case AMQPAbstractCollection::T_TABLE:
                $this->writeTable($val);
                break;
            case AMQPAbstractCollection::T_VOID:
                break;
            case AMQPAbstractCollection::T_BYTES:
                $this->writeLongstr($val);
                break;
            default:
                throw new AMQPInvalidArgumentException(sprintf(
                    'Unsupported type "%s"',
                    $type
                ));
        }
    }
}

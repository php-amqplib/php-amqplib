<?php

namespace PhpAmqpLib\Wire;

use PhpAmqpLib\Wire\AMQPDecimal;
use PhpAmqpLib\Wire\BufferedInput;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPOutOfBoundsException;

class AMQPReader
{
    public function __construct($str, $sock=null)
    {
        $this->str = $str;
        if ($sock !== null) {
            $this->sock = new BufferedInput($sock);
        } else {
            $this->sock = null;
        }
        $this->offset = 0;

        $this->bitcount = $this->bits = 0;

        if(((int) 4294967296)!=0)
            $this->is64bits = true;
        else
            $this->is64bits = false;

        if(!function_exists("bcmul")) {
            throw new AMQPRuntimeException("'bc math' module required");
        }

        $this->buffer_read_timeout = 5; // in seconds
    }

    public function close()
    {
        if($this->sock)
            $this->sock->close();
    }

    public function read($n)
    {
        $this->bitcount = $this->bits = 0;

        return $this->rawread($n);
    }

    private function rawread($n)
    {
        if ($this->sock) {
            $res = '';
            $read = 0;

            while ($read < $n && !feof($this->sock->real_sock()) &&
                    (false !== ($buf = fread($this->sock->real_sock(), $n - $read)))) {

                // get status of socket to determine whether or not it has timed out
                $info = stream_get_meta_data($this->sock->real_sock());
                if($info['timed_out']) {
                    throw new \Exception('Error reading data. Socket connection timed out');
                }

                $read += strlen($buf);
                $res .= $buf;
            }

            if (strlen($res)!=$n) {
                throw new AMQPRuntimeException("Error reading data. Received " .
                                     strlen($res) . " instead of expected $n bytes");
            }

            $this->offset += $n;
        } else {
            if(strlen($this->str) < $n)
                throw new AMQPRuntimeException("Error reading data. Requested $n bytes while string buffer has only " .
                                     strlen($this->str));
            $res = substr($this->str,0,$n);
            $this->str = substr($this->str,$n);
            $this->offset += $n;
        }

        return $res;
    }

    public function read_bit()
    {
        if (!$this->bitcount) {
            $this->bits = ord($this->rawread(1));
            $this->bitcount = 8;
        }
        $result = ($this->bits & 1) == 1;
        $this->bits >>= 1;
        $this->bitcount -= 1;

        return $result;
    }

    public function read_octet()
    {
        $this->bitcount = $this->bits = 0;
        list(,$res) = unpack('C', $this->rawread(1));

        return $res;
    }

    public function read_short()
    {
        $this->bitcount = $this->bits = 0;
        list(,$res) = unpack('n', $this->rawread(2));

        return $res;
    }

    /**
     * Reads 32 bit integer in big-endian byte order.
     *
     * On 64 bit systems it will return always usngined int
     * value in 0..2^32 range.
     *
     * On 32 bit systems it will return signed int value in
     * -2^31...+2^31 range.
     *
     * Use with caution!
     */
    public function read_php_int()
    {
        list(,$res) = unpack('N', $this->rawread(4));
        if ($this->is64bits) {
            $sres = sprintf ( "%u", $res );

            return (int) $sres;
        } else {
            return $res;
        }
    }

    // PHP does not have unsigned 32 bit int,
    // so we return it as a string
    public function read_long()
    {
        $this->bitcount = $this->bits = 0;
        list(,$res) = unpack('N', $this->rawread(4));
        $sres = sprintf ( "%u", $res );

        return $sres;
    }

    private function read_signed_long()
    {
        $this->bitcount = $this->bits = 0;
        // In PHP unpack('N') always return signed value,
        // on both 32 and 64 bit systems!
        list(,$res) = unpack('N', $this->rawread(4));

        return $res;
    }

    // Even on 64 bit systems PHP integers are singed.
    // Since we need an unsigned value here we return it
    // as a string.
    public function read_longlong()
    {
        $this->bitcount = $this->bits = 0;
        $hi = unpack('N', $this->rawread(4));
        $lo = unpack('N', $this->rawread(4));

        // workaround signed/unsigned braindamage in php
        $hi = sprintf ( "%u", $hi[1] );
        $lo = sprintf ( "%u", $lo[1] );

        return bcadd(bcmul($hi, "4294967296" ), $lo);
    }

    /**
     * Read a utf-8 encoded string that's stored in up to
     * 255 bytes.  Return it decoded as a PHP unicode object.
     */
    public function read_shortstr()
    {
        $this->bitcount = $this->bits = 0;
        list(,$slen) = unpack('C', $this->rawread(1));

        return $this->rawread($slen);
    }

    /**
     * Read a string that's up to 2**32 bytes, the encoding
     * isn't specified in the AMQP spec, so just return it as
     * a plain PHP string.
     */
    public function read_longstr()
    {
        $this->bitcount = $this->bits = 0;
        $slen = $this->read_php_int();
        if($slen<0)
            throw new AMQPOutOfBoundsException("Strings longer than supported on this platform");

        return $this->rawread($slen);
    }

    /**
     * Read and AMQP timestamp, which is a 64-bit integer representing
     * seconds since the Unix epoch in 1-second resolution.
     */
    public function read_timestamp()
    {
        return $this->read_longlong();
    }

    /**
     * Read an AMQP table, and return as a PHP array. keys are strings,
     * values are (type,value) tuples.
     */
    public function read_table()
    {
        $this->bitcount = $this->bits = 0;
        $tlen = $this->read_php_int();

        if($tlen<0) {
            throw new AMQPOutOfBoundsException("Table is longer than supported");
        }

        $table_data = new AMQPReader($this->rawread($tlen));
        $result = array();
        while ($table_data->tell() < $tlen) {
            $name = $table_data->read_shortstr();
            $ftype = $table_data->rawread(1);
            $val = $table_data->read_value($ftype);
            $result[$name] = array($ftype,$val);
        }

        return $result;
    }

    /**
     * Reads the array in the next value.
     *
     * @return array
     */
    public function read_array()
    {
        $this->bitcount = $this->bits = 0;

        // Determine array length and its end position
        $arrayLength = $this->read_php_int();
        $endOffset = $this->offset + $arrayLength;

        $result = array();
        // Read values until we reach the end of the array
        while ($this->offset < $endOffset) {
            $fieldType = $this->rawread(1);
            $result[] = $this->read_value($fieldType);
        }

        return $result;
    }

    /**
     * Reads the next value as the provided field type.
     *
     * @param  string $fieldType the char field type
     * @return mixed
     */
    public function read_value($fieldType)
    {
        $this->bitcount = $this->bits = 0;

        $val = NULL;
        switch ($fieldType) {
            case 'S': // Long string
                $val = $this->read_longstr();
                break;
            case 'I': // Signed 32-bit
                $val = $this->read_signed_long();
                break;
            case 'D': // Decimal
                $e = $this->read_octet();
                $n = $this->read_signed_long();
                $val = new AMQPDecimal($n, $e);
                break;
            case 't':
                $val = $this->read_octet();
                break;
            case 'l':
                $val = $this->read_longlong();
                break;
            case 'T': // Timestamp
                $val = $this->read_timestamp();
                break;
            case 'F': // Table
                $val = $this->read_table();
                break;
            case 'A': // Array
                $val = $this->read_array();
                break;
            default:
                // UNKNOWN TYPE
                throw new \RuntimeException("Usupported table field type {$fieldType}");
                break;
        }

        return $val;
    }

    protected function tell()
    {
        return $this->offset;
    }

}

<?php

  /**
   * AMQP protocol serialization/deserialization to/from wire format.
   *
   * http://code.google.com/p/php-amqplib/
   * Vadim Zaliva <lord@crocodile.org>
   *
   *
   * To understand all signed/unsinged and 32/64 bit madness in this
   * code, please read first the following article:
   *
   * http://www.mysqlperformanceblog.com/2007/03/27/integers-in-php-running-with-scissors-and-portability/
   */

require_once('hexdump.inc');

  /**
   * AMQP protocol decimal value.
   *
   * Values are represented as (n,e) pairs. The actual value
   * is n * 10^(-e).
   *
   * From 0.8 spec: Decimal values are
   * not intended to support floating point values, but rather
   * business values such as currency rates and amounts. The
   * 'decimals' octet is not signed.
  */
class AMQPDecimal
{
    public function __construct($n, $e)
    {
        if($e < 0)
            throw new Exception("Decimal exponent value must be unsigned!");
        $this->n = $n;
        $this->e = $e;
    }

    public function asBCvalue()
    {
        return bcdiv($this->n, bcpow(10,$this->e));
    }
}

class AMQPWriter
{
    public function __construct()
    {
        $this->out = "";
        $this->bits = array();
        $this->bitcount = 0;
    }

    private static function chrbytesplit($x, $bytes)
    {
        return array_map('chr', AMQPWriter::bytesplit($x,$bytes));
    }

    /**
     * Splits number (could be either int or string) into array of byte
     * values (represented as integers) in big-endian byte order.
     */
    private static function bytesplit($x, $bytes)
    {
        if(is_int($x))
        {
            if($x<0)
                $x = sprintf("%u", $x);
        }
                
        $res = array();
        for($i=0;$i<$bytes;$i++)
        {
            $b = bcmod($x,'256');
            array_unshift($res,(int)$b);
            $x=bcdiv($x,'256', 0);
        }
        if($x!=0)
            throw new Exception("Value too big!");
        return $res;
    }
    
    private function flushbits()
    {
        if(count($this->bits))
        {
            $this->out .= implode("", array_map('chr',$this->bits));
            $this->bits = array();
            $this->bitcount = 0;
        }
    }

    /**
     * Get what's been encoded so far.
     */
    public function getvalue()
    {
        $this->flushbits();
        return $this->out;
    }

    /**
     * Write a plain Python string, with no special encoding.
     */
    public function write($s)
    {
        $this->flushbits();
        $this->out .= $s;
    }

    /**
     * Write a boolean value.
     */
    public function write_bit($b)
    {
        if($b)
            $b = 1;
        else
            $b = 0;
        $shift = $this->bitcount % 8;
        if($shift == 0)
            $last = 0;
        else
            $last = array_pop($this->bits);
        
        $last |= ($b << $shift);
        array_push($this->bits, $last);
            
        $this->bitcount += 1;
    }

    /**
     * Write an integer as an unsigned 8-bit value.
     */
    public function write_octet($n)
    {
        if($n < 0 || $n > 255)
            throw new Exception('Octet out of range 0..255');
        $this->flushbits();
        $this->out .= chr($n);
    }

    /**
     * Write an integer as an unsigned 16-bit value.
     */
    public function write_short($n)
    {
        if($n < 0 ||  $n > 65535)
            throw new Exception('Octet out of range 0..65535');
        $this->flushbits();
        $this->out .= pack('n', $n);
    }

    /**
     * Write an integer as an unsigned 32-bit value.
     */
    public function write_long($n)
    {
        $this->flushbits();
        $this->out .= implode("", AMQPWriter::chrbytesplit($n,4));
    }

    private function write_signed_long($n)
    {
        $this->flushbits();
        // although format spec for 'N' mentions unsigned
        // it will deal with sinned integers as well. tested.
        $this->out .= pack('N', $n);
    }

    /**
     * Write an integer as an unsigned 64-bit value.
     */
    public function write_longlong($n)
    {
        $this->flushbits();
        $this->out .= implode("", AMQPWriter::chrbytesplit($n,8));
    }

    /**
     * Write a string up to 255 bytes long after encoding.
     * Assume UTF-8 encoding.
     */
    public function write_shortstr($s)
    {
        $this->flushbits();
        if(strlen($s) > 255)
            throw new Exception('String too long');
        $this->write_octet(strlen($s));
        $this->out .= $s;
    }


    /*
     * Write a string up to 2**32 bytes long.  Assume UTF-8 encoding.
     */
    public function write_longstr($s)
    {
        $this->flushbits();
        $this->write_long(strlen($s));
        $this->out .= $s;
    }


    /**
     * Write unix time_t value as 64 bit timestamp.
     */
   public function write_timestamp($v)
   {
       $this->write_longlong($v);
   }

   /**
    * Write PHP array, as table. Input array format: keys are strings,
    * values are (type,value) tuples.
    */
    public function write_table($d)
    {
        $this->flushbits();
        $table_data = new AMQPWriter();
        foreach($d as $k=>$va)
        {
            list($ftype,$v) = $va;
            $table_data->write_shortstr($k);
            if($ftype=='S')
            {
                $table_data->write('S');
                $table_data->write_longstr($v);
            } else if($ftype=='I')
            {
                $table_data->write('I');
                $table_data->write_signed_long($v);
            } else if($ftype=='D')
            {
                // 'D' type values are passed AMQPDecimal instances.
                $table_data->write('D');
                $table_data->write_octet($v->e);
                $table_data->write_signed_long($v->n);
            } else if($ftype=='T')
            {
                $table_data->write('T');
                $table_data->write_timestamp($v);
            } else if($ftype=='F')
            {
                $table_data->write('F');
                $table_data->write_table($v);
            }
        }
        $table_data = $table_data->getvalue();
        $this->write_long(strlen($table_data));
        $this->write($table_data);
    }
}

class AMQPReader
{
    public function __construct($str, $sock=NULL)
    {
        $this->str = $str;
        if ($sock !== NULL)
        {
            $this->sock = new BufferedInput($sock);
        } else
        {
            $this->sock = NULL;
        }
        $this->offset = 0;

        $this->bitcount = $this->bits = 0;

        if(((int)4294967296)!=0)
            $this->is64bits = true;
        else
            $this->is64bits = false;

        if(!function_exists("bcmul"))
            throw new Exception("'bc math' module required");
            
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
        if($this->sock)
        {
            $res = '';
            $read = 0;
            
            $start = time();
            while($read < $n && !feof($this->sock->real_sock()) &&
                  (false !== ($buf = fread($this->sock->real_sock(), $n - $read))))
            {
                if ($buf == '')
                {
                    usleep(100);
                }
                else
                    $start = time();                
                    
                $read += strlen($buf);
                $res .= $buf;
            }

            if(strlen($res)!=$n)
                throw new Exception ("Error reading data. Recevived " .
                                     strlen($res) . " instead of expected $n bytes");
            $this->offset += $n;
        } else
        {
            if(strlen($this->str) < $n)
                throw new Exception ("Error reading data. Requested $n bytes while string buffer has only " .
                                     strlen($this->str));
            $res = substr($this->str,0,$n);
            $this->str = substr($this->str,$n);
            $this->offset += $n;
        }
        return $res;
    }

    public function read_bit()
    {
        if(!$this->bitcount)
        {
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
        if($this->is64bits)
        {
            $sres = sprintf ( "%u", $res );
            return (int)$sres;
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
     * 255 bytes.  Return it decoded as a Python unicode object.
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
            throw new Exception("Strings longer than supported on this platform");
        return $this->rawread($slen);
    }
    
    /**
     * Read and AMQP timestamp, which is a 64-bit integer representing
     * seconds since the Unix epoch in 1-second resolution.
     */
    function read_timestamp()
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
        if($tlen<0)
            throw new Exception("Table is longer than supported");
        $table_data = new AMQPReader($this->rawread($tlen));
        $result = array();
        while($table_data->tell() < $tlen)
        {
            $name = $table_data->read_shortstr();
            $ftype = $table_data->rawread(1);
            if($ftype == 'S') {
                $val = $table_data->read_longstr();
            } else if($ftype == 'I') {
                $val = $table_data->read_signed_long();
            } else if($ftype == 'D')
            {
                $e = $table_data->read_octet();
                $n = $table_data->read_signed_long();
                $val = new AMQPDecimal($n, $e);
            } else if($ftype == 'T')
            {
                $val = $table_data->read_timestamp();
            } else if($ftype == 'F')
            {
                $val = $table_data->read_table(); // recursion
            } else {
                error_log("Usupported table field type $ftype");
                $val = NULL;
            }
            $result[$name] = array($ftype,$val);
        }
        return $result;
    }

    
    protected function tell()
    {
        return $this->offset;
    }
    
}


/**
 * Abstract base class for AMQP content.  Subclasses should override
 * the PROPERTIES attribute.
 */
class GenericContent
{
    protected static $PROPERTIES = array(
        "dummy" => "shortstr"
    );

    public function __construct($props, $prop_types=NULL)
    {
        if($prop_types)
            $this->prop_types = $prop_types;
        else
            $this->prop_types = GenericContent::$PROPERTIES;
        $d = array();
        if ($props)
            $d = array_intersect_key($props, $this->prop_types);
        else
            $d = array();
        $this->properties = $d;
    }


    /**
     * Look for additional properties in the 'properties' dictionary,
     * and if present - the 'delivery_info' dictionary.
     */
    public function get($name)
    {
        if(array_key_exists($name,$this->properties))
            return $this->properties[$name];
        
        if(isset($this->delivery_info))
            if(array_key_exists($name,$this->delivery_info))
                return $this->delivery_info[$name];
        
        throw new Exception("No such property");
    }


    /**
     * Given the raw bytes containing the property-flags and
     * property-list from a content-frame-header, parse and insert
     * into a dictionary stored in this object as an attribute named
     * 'properties'.
     */
    public function load_properties($raw_bytes)
    {
        $r = new AMQPReader($raw_bytes);

        // Read 16-bit shorts until we get one with a low bit set to zero
        $flags = array();
        while(true)
        {
            $flag_bits = $r->read_short();
            array_push($flags, $flag_bits);
            if(($flag_bits & 1) == 0)
                break;
        }

        $shift = 0;
        $d = array();
        foreach ($this->prop_types as $key => $proptype)
        {
            if($shift == 0) {
                if(!$flags) {
                    break;
                }
                $flag_bits = array_shift($flags);
                $shift = 15;
            }
            if($flag_bits & (1 << $shift))
                $d[$key] = call_user_func(array($r,"read_".$proptype));
            $shift -= 1;
        }
        $this->properties = $d;
    }


    /**
     * serialize the 'properties' attribute (a dictionary) into the
     * raw bytes making up a set of property flags and a property
     * list, suitable for putting into a content frame header.
     */
    public function serialize_properties()
    {
        $shift = 15;
        $flag_bits = 0;
        $flags = array();
        $raw_bytes = new AMQPWriter();
        foreach ($this->prop_types as $key => $proptype)
        {
            if(array_key_exists($key,$this->properties))
                $val = $this->properties[$key];
            else
                $val = NULL;
            if($val != NULL)
            {
                if($shift == 0)
                {
                    array_push($flags, $flag_bits);
                    $flag_bits = 0;
                    $shift = 15;
                }
                
                $flag_bits |= (1 << $shift);
                if($proptype != "bit")
                    call_user_func(array($raw_bytes, "write_" . $proptype),
                                   $val);
            }
            $shift -= 1;
        }
        array_push($flags, $flag_bits);
        $result = new AMQPWriter();
        foreach($flags as $flag_bits)
            $result->write_short($flag_bits);
        $result->write($raw_bytes->getvalue());
        
        return $result->getvalue();
    }
}

class BufferedInput
{
    public function __construct($sock)
    {
        $this->block_size = 8192;

        $this->sock = $sock;
        $this->reset("");
        
    }
    
    public function real_sock()
    {
        return $this->sock;
    }
    
    public function read($n)
    {
        if ($this->offset >= strlen($this->buffer))
        {
            if (!($rv = $this->populate_buffer()))
            {
                return $rv;
            }
        }
        return $this->read_buffer($n);
    }
    
    public function close()
    {
        fclose($this->sock);
        $this->reset("");
    }
    
    private function read_buffer($n)
    {
        $n = min($n, strlen($this->buffer) - $this->offset);
        if ($n === 0)
        {
            // substr("", 0, 0) => FALSE, which screws up read loops that are
            // expecting non-blocking reads to return "". This avoids that edge
            // case when the buffer is empty/used up.
            return "";
        }
        $block = substr($this->buffer, $this->offset, $n);
        $this->offset += $n;
        return $block;
    }
    
    private function reset($block)
    {
        $this->buffer = $block;
        $this->offset = 0;
    }
    
    private function populate_buffer()
    {
        if(feof($this->sock))
        {
            $this->reset("");
            return FALSE;
        }
        
        $block = fread($this->sock, $this->block_size);
        if ($block !== FALSE)
        {
            $this->reset($block);
            return TRUE;
        } else
        {
            return $block;
        }
    }
}
?>
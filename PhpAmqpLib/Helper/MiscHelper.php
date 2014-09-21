<?php
namespace PhpAmqpLib\Helper;

class MiscHelper
{
    /**
     * @param string $string
     */
    public static function debug_msg($string)
    {
        echo $string . PHP_EOL;
    }

    /**
     * @param $a
     * @return string
     */
    public static function methodSig($a)
    {
        if (is_string($a)) {
            return $a;
        }

        return sprintf('%d,%d', $a[0], $a[1]);
    }

    /**
     * @param $bytes
     */
    public static function saveBytes($bytes)
    {
        $fh = fopen('/tmp/bytes', 'wb');
        fwrite($fh, $bytes);
        fclose($fh);
    }

    /**
     * Gets a number (either int or float) and returns an array containing its integer part as first element and its
     * decimal part mutliplied by 10^6. Useful for some PHP stream functions that need seconds and microseconds as
     * different arguments
     *
     * @param $number
     * @return array
     */
    public static function splitSecondsMicroseconds($number)
    {
        return array(floor($number), ($number - floor($number)) * 1000000);
    }

    /**
     * View any string as a hexdump.
     *
     * This is most commonly used to view binary data from streams
     * or sockets while debugging, but can be used to view any string
     * with non-viewable characters.
     *
     * @version     1.3.2
     * @author      Aidan Lister <aidan@php.net>
     * @author      Peter Waller <iridum@php.net>
     * @link        http://aidanlister.com/repos/v/function.hexdump.php
     *
     * @param string $data The string to be dumped
     * @param bool $htmloutput Set to false for non-HTML output
     * @param bool $uppercase Set to true for uppercase hex
     * @param bool $return Set to true to return the dump
     * @return string
     */
    public static function hexdump($data, $htmloutput = true, $uppercase = false, $return = false)
    {
        // Init
        $hexi = '';
        $ascii = '';
        $dump = ($htmloutput === true) ? '<pre>' : '';
        $offset = 0;
        $len = mb_strlen($data, 'ASCII');

        // Upper or lower case hexidecimal
        $x = ($uppercase === false) ? 'x' : 'X';

        // Iterate string
        for ($i = $j = 0; $i < $len; $i++) {
            // Convert to hexidecimal
            $hexi .= sprintf('%02$x ', ord($data[$i]));

            // Replace non-viewable bytes with '.'
            if (ord($data[$i]) >= 32) {
                $ascii .= ($htmloutput === true) ?
                    htmlentities($data[$i]) :
                    $data[$i];
            } else {
                $ascii .= '.';
            }

            // Add extra column spacing
            if ($j === 7) {
                $hexi .= ' ';
                $ascii .= ' ';
            }

            // Add row
            if (++$j === 16 || $i === $len - 1) {
                // Join the hexi / ascii output
                $dump .= sprintf('%04$x  %-49s  %s', $offset, $hexi, $ascii);

                // Reset vars
                $hexi = $ascii = '';
                $offset += 16;
                $j = 0;

                // Add newline
                if ($i !== $len - 1) {
                    $dump .= PHP_EOL;
                }
            }
        }

        // Finish dump
        $dump .= $htmloutput === true ? '</pre>' : '';
        $dump .= PHP_EOL;

        // Output method
        if ($return) {
            return $dump;
        }

        echo $dump;
    }
}

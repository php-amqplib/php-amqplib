<?php

namespace PhpAmqpLib\Helper;

if (class_exists('phpseclib\Math\BigInteger')) {
    class BigInteger extends \phpseclib\Math\BigInteger
    {
    }
} elseif (class_exists('phpseclib3\Math\BigInteger')) {
    class BigInteger extends \phpseclib3\Math\BigInteger
    {
    }
} elseif (class_exists('phpseclib4\Math\BigInteger')) {
    class BigInteger extends \phpseclib4\Math\BigInteger
    {
    }
} else {
    throw new \RuntimeException('Cannot find supported phpseclib/phpseclib library');
}

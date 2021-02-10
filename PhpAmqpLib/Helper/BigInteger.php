<?php

namespace PhpAmqpLib\Helper;

if (class_exists('phpseclib\Math\BigInteger')) {
    class_alias('phpseclib\Math\BigInteger', 'PhpAmqpLib\Helper\BigInteger');
} elseif (class_exists('phpseclib3\Math\BigInteger')) {
    class_alias('phpseclib3\Math\BigInteger', 'PhpAmqpLib\Helper\BigInteger');
} else {
    throw new \RuntimeException('Cannot find supported phpseclib/phpseclib library');
}

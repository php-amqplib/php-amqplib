<?php
namespace PhpAmqpLib\Wire;

use PhpAmqpLib\Exception\AMQPOutOfBoundsException;
use phpseclib\Math\BigInteger;

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
    /** @var int */
    protected $n;

    /** @var int */
    protected $e;

    /**
     * @param int $n
     * @param int $e
     * @throws \PhpAmqpLib\Exception\AMQPOutOfBoundsException
     */
    public function __construct($n, $e)
    {
        if ($e < 0) {
            throw new AMQPOutOfBoundsException('Decimal exponent value must be unsigned!');
        }

        $this->n = $n;
        $this->e = $e;
    }

    /**
     * @return string
     */
    public function asBCvalue()
    {
        $n = new BigInteger($this->n);
        $e = new BigInteger('1' . str_repeat('0', $this->e));
        list($q) = $n->divide($e);
        return $q->toString();
    }

    /**
     * @return int
     */
    public function getE()
    {
        return $this->e;
    }

    /**
     * @return int
     */
    public function getN()
    {
        return $this->n;
    }
}

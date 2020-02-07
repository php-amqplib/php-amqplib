<?php

namespace PhpAmqpLib\Helper;

use InvalidArgumentException;

class Assert
{
    /**
     * @param mixed $argument
     * @throws \InvalidArgumentException
     */
    public static function isCallable($argument)
    {
        if (!is_callable($argument)) {
            throw new InvalidArgumentException(sprintf(
                'Given argument "%s" should be callable. %s type was given.',
                $argument,
                gettype($argument)
            ));
        }
    }
}

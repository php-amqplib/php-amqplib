<?php

namespace PhpAmqpLib\Helper;

use Kdyby;
use Nette;
use PhpAmqpLib\Exception\AMQPMemberAccessException;



abstract class Object
{

    public function __call($name, $arguments)
    {
        throw new AMQPMemberAccessException("Method " . get_called_class() . "::{$name}() is not implemented");
    }



    public function &__get($name)
    {
        throw new AMQPMemberAccessException("Property " . get_called_class() . "::\${$name} is not defined");
    }



    public function __set($name, $value)
    {
        throw new AMQPMemberAccessException("Property " . get_called_class() . "::\${$name} is not defined");
    }



    public function __isset($name)
    {
        throw new AMQPMemberAccessException("Property " . get_called_class() . "::\${$name} is not defined");
    }



    public function __unset($name)
    {
        throw new AMQPMemberAccessException("Property " . get_called_class() . "::\${$name} is not defined");
    }

}

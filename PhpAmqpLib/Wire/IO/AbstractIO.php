<?php

namespace PhpAmqpLib\Wire\IO;

use PhpAmqpLib\Helper\Object;

abstract class AbstractIO extends Object
{

    abstract public function read($n);



    abstract public function write($data);



    abstract public function close();



    abstract public function select($sec, $usec);



    abstract public function connect();



    abstract public function reconnect();

}

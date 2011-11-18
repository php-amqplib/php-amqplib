<?php

namespace PhpAmqpLib\Debug;

class MethodSignature
{
    public function methodSig($a)
    {
        if(is_string($a))
            return $a;
        else
            return sprintf("%d,%d",$a[0] ,$a[1]);
    }
}
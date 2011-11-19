<?php

function debug_msg($s)
{
  echo $s, "\n";
}

function methodSig($a)
{
    if(is_string($a))
        return $a;
    else
        return sprintf("%d,%d",$a[0] ,$a[1]);
}

function saveBytes($bytes)
{
    $fh = fopen('/tmp/bytes', 'wb');
    fwrite($fh, $bytes);
    fclose($fh);
}
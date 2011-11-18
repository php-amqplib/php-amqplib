<?php

//TODO add make target to get the UniversalClassLoader maybe as git submodule.
require_once(__DIR__ . '/vendor/symfony/Symfony/Component/ClassLoader/UniversalClassLoader.php');

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
            'PhpAmqpLib' => __DIR__,
        ));

$loader->register();

require_once(__DIR__ . '/hexdump.inc');
require_once(__DIR__ . '/PhpAmqpLib/Helper/misc.php');
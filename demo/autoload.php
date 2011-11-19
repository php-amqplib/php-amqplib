<?php

require_once(__DIR__ . '/../vendor/symfony/Symfony/Component/ClassLoader/UniversalClassLoader.php');

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
            'PhpAmqpLib' => __DIR__ . '/..',
        ));

$loader->register();
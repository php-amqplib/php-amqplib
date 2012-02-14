# php-amqplib #

[![Build Status](https://secure.travis-ci.org/videlalvaro/php-amqplib.png)](http://travis-ci.org/videlalvaro/php-amqplib)

This library is a _pure PHP_ implementation of the AMQP protocol. It's been tested against [RabbitMQ](http://www.rabbitmq.com/).

## NOTE ##

This library is a fork of the [php-amqplib](http://code.google.com/p/php-amqplib/) library.

At The Netcircle we modified that library in order to work with PHP 5.3 Strict.

Also we improved the debug method to increase performance.

We use it daily in prod for sending/consuming 600K + messages per day.

## BC BREAKING CHANGES ##

As of November 2011 I retook the development of this library therefore I __tagged__ the __previous version__ of the library [here](https://github.com/videlalvaro/php-amqplib/tarball/v1.0). If you are looking for the old library then use the code on that tag.

If you are going to use it in a new project I advice that you use the current master branch. There are many performance improvements in that branch and I'm adding more and more tests to it.

Besides that the library has been refactored to use PHP 5.3 `namespaces`. The classes have been split into their separate files and so on. The idea is to make the library easier to test.

To be sure that what you are downloading _works™_ you can always check the build status of the library on [travis-ci](http://travis-ci.org/#!/videlalvaro/php-amqplib).

## Setup ##

Get the library source code:

    $ git clone git://github.com/videlalvaro/php-amqplib.git

This library uses the `Symfony` default `UniversalClassLoader` so you will have to run the following command to download it as a submodule:

    $ make

Start your RabbitMQ server and run tests:

    $ make test

## Usage ##

With RabbitMQ running open two Terminals and on the first one execute the following commands to start the consumer:

    $ cd php-amqplib/demo
    $ php amqp_consumer.php

Then on the other Terminal do:

    $ cd php-amqplib/demo
    $ php amqp_publisher.php some text to publish

You should see the message arriving to the process on the other Terminal

Then to stop the consumer, send to it the `quit` message:

    $ php amqp_publisher.php quit

If you need to attent your sockets try non blocking consumer.

    $ php amqp_consumer_non_blocking.php

## Loading Classes ##

The library uses the [Symfony ClassLoader component](https://github.com/symfony/ClassLoader) in order to use a standard way of class loading.

If you want to see how to use the component with this library you can take a look at the file `demo/autoload.php`:

    <?php

    require_once(__DIR__ . '/../vendor/symfony/Symfony/Component/ClassLoader/UniversalClassLoader.php');

    use Symfony\Component\ClassLoader\UniversalClassLoader;

    $loader = new UniversalClassLoader();
    $loader->registerNamespaces(array(
                'PhpAmqpLib' => __DIR__ . '/..',
            ));

    $loader->register();

## Debugging ##

If you want to know what's going on at a protocol level then add the following constant to your code:

    <?php
    define('AMQP_DEBUG', true);

    ... more code

    ?>

## Benchmarks ##

To run the publishing/consume benchmark type:

    $ make benchmark

## Original README: ##

Below is the original README file content. Credits goes to the original authors.

PHP library implementing Advanced Message Queuing Protocol (AMQP).

The library is port of python code of py-amqplib
http://barryp.org/software/py-amqplib/

It have been tested with RabbitMQ server.

Project home page: http://code.google.com/p/php-amqplib/

For discussion, please join the group:

http://groups.google.com/group/php-amqplib-devel

For bug reports, please use bug tracking system at the project page.

Patches are very welcome!

Author: Vadim Zaliva <lord@crocodile.org>



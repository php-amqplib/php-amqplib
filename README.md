# NOTE #

This library is a fork of the [php-amqplib](http://code.google.com/p/php-amqplib/) library.

We modified that library in order to work with PHP 5.3 Strict.

Also we improved the debug method to increase performance.

We use it daily in prod for sending/consuming 600K + messages per day.

Below is the original README file content. Credits goes to the original authors.

## Usage ##

Start your RabbitMQ server, then get the source:

    $ git clone git://github.com/tnc/php-amqplib.git

Open two Terminals and on the first one execute the following commands to start the consumer:

    $ cd php-amqplib/demo
    $ php amqp_consumer.php

Then on the other Terminal do:

    $ cd php-amqplib/demo
    $ php amqp_publisher.php some text to publish

You should see the message arriving to the process on the other Terminal

Then to stop the consumer, send to it the `quit` message:

    $ php amqp_publisher.php quit

# Debugging #

If you want to know what's going on at a protocol level then add the following constant to your code:

    <?php
    define('AMQP_DEBUG', true);

    ... more code

    ?>


# Original README: #

PHP library implementing Advanced Message Queuing Protocol (AMQP).

The library is port of python code of py-amqplib
http://barryp.org/software/py-amqplib/

It have been tested with RabbitMQ server.

Project home page: http://code.google.com/p/php-amqplib/

For discussion, please join the group:

http://groups.google.com/group/php-amqplib-devel

For bug reports, please use bug tracking system at the project page.

Patched are very welcome!

Author: Vadim Zaliva <lord@crocodile.org>



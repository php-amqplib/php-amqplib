<?php

  /**
   * AMQP protocol serialization/deserialization to/from wire format.
   *
   * http://code.google.com/p/php-amqplib/
   * Vadim Zaliva <lord@crocodile.org>
   *
   *
   * To understand all signed/unsinged and 32/64 bit madness in this
   * code, please read first the following article:
   *
   * http://www.mysqlperformanceblog.com/2007/03/27/integers-in-php-running-with-scissors-and-portability/
   */

require_once('hexdump.inc');
require_once(__DIR__ . '/wire/AMQPDecimal.php');
require_once(__DIR__ . '/wire/AMQPWriter.php');
require_once(__DIR__ . '/wire/AMQPReader.php');
require_once(__DIR__ . '/wire/GenericContent.php');

?>
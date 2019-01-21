<?php

define('HOST', isset($_ENV['TEST_RABBITMQ_HOST']) ? $_ENV['TEST_RABBITMQ_HOST'] : 'localhost');
define('PORT', isset($_ENV['TEST_RABBITMQ_PORT']) ? (int)$_ENV['TEST_RABBITMQ_PORT'] : 5672);
define('USER', isset($_ENV['TEST_RABBITMQ_USER']) ? $_ENV['TEST_RABBITMQ_USER'] : 'guest');
define('PASS', isset($_ENV['TEST_RABBITMQ_PASS']) ? $_ENV['TEST_RABBITMQ_PASS'] : 'guest');
define('VHOST', '/');
define('AMQP_DEBUG', isset($_ENV['TEST_AMQP_DEBUG']) ? (bool)$_ENV['TEST_AMQP_DEBUG'] : false);

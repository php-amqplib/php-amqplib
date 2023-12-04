<?php

define('HOST', getenv('TEST_RABBITMQ_HOST') ? getenv('TEST_RABBITMQ_HOST') : 'localhost');
define('HOST6', getenv('TEST_RABBITMQ_HOST6') ? getenv('TEST_RABBITMQ_HOST6') : '[::1]');
define('PORT', getenv('TEST_RABBITMQ_PORT') ? getenv('TEST_RABBITMQ_PORT') : 5672);
define('USER', getenv('TEST_RABBITMQ_USER') ? getenv('TEST_RABBITMQ_USER') : 'guest');
define('PASS', getenv('TEST_RABBITMQ_PASS') ? getenv('TEST_RABBITMQ_PASS') : 'guest');
define('VHOST', '/');
define('AMQP_DEBUG', getenv('TEST_AMQP_DEBUG') !== false ? (bool)getenv('TEST_AMQP_DEBUG') : false);

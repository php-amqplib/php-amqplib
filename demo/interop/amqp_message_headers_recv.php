<?php

use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use PhpAmqpLib\Interop\AmqpConnectionFactory;

include(__DIR__ . '/config.php');

$bindingKeys = array_slice($argv, 1);
if (empty($bindingKeys)) {
    file_put_contents('php://stderr', "Usage: $argv[0] [binding_key]\n");
    exit(1);
}

$exchangeName = 'topic_headers_test';

$context = (new AmqpConnectionFactory(AMQP_DSN))->createContext();

$queue = $context->createTemporaryQueue();

$topic = $context->createTopic($exchangeName);
$topic->setType(AmqpTopic::TYPE_TOPIC);
$topic->addFlag(AmqpTopic::FLAG_AUTODELETE);
$context->declareTopic($topic);

foreach ($bindingKeys as $bindingKey) {
    $context->bind(new AmqpBind($topic, $queue, $bindingKey));
}

$consumer = $context->createConsumer($queue);
$consumer->addFlag(AmqpConsumer::FLAG_NOACK);
$consumer->addFlag(AmqpConsumer::FLAG_EXCLUSIVE);

while (true) {
    if (false == $message = $consumer->receive(1000)) {
        continue;
    }

    echo PHP_EOL . ' [x] ', $message->getRoutingKey(), ':', $message->getBody(), "\n";
    echo 'Message headers follows' . PHP_EOL;
    var_dump($message->getProperties());
    echo PHP_EOL;
}

<?php
namespace PhpAmqpLib\Exchange;

class AMQPExchange
{
    const AMQP_EX_TYPE_DIRECT = 'direct';
    const AMQP_EX_TYPE_FANOUT = 'fanout';
    const AMQP_EX_TYPE_TOPIC = 'topic';
    const AMQP_EX_TYPE_HEADERS = 'headers';
}
<?php

namespace PhpAmqpLib\Helper\Protocol;

use PhpAmqpLib\Wire\AMQPWriter;

class FrameBuilder
{
    public function channelClose($reply_code, $reply_text, $class_id, $method_id)
    {
        $args = new AMQPWriter();
        $args->write_short($reply_code)
             ->write_shortstr($reply_text)
             ->write_short($class_id)
             ->write_short($method_id);
        return $args;
    }

    public function flow($active)
    {
        $args = new AMQPWriter();
        $args->write_bit($active);
        return $args;
    }

    public function xFlowOk($active)
    {
        $args = new AMQPWriter();
        $args->write_bit($active);
        return $args;
    }

    public function xOpen($out_of_band)
    {
        $args = new AMQPWriter();
        $args->write_shortstr($out_of_band);
        return $args;
    }

    public function accessRequest($realm,
                                    $exclusive,
                                    $passive,
                                    $active,
                                    $write,
                                    $read)
    {
        $args = new AMQPWriter();
        $args->write_shortstr($realm)
             ->write_bit($exclusive)
             ->write_bit($passive)
             ->write_bit($active)
             ->write_bit($write)
             ->write_bit($read);
        return $args;
    }

    public function exchangeDeclare($exchange,
                                     $type,
                                     $passive,
                                     $durable,
                                     $auto_delete,
                                     $internal,
                                     $nowait,
                                     $arguments,
                                     $ticket)
    {
        $args = new AMQPWriter();
        $args->write_short($ticket)
             ->write_shortstr($exchange)
             ->write_shortstr($type)
             ->write_bit($passive)
             ->write_bit($durable)
             ->write_bit($auto_delete)
             ->write_bit($internal)
             ->write_bit($nowait)
             ->write_table($arguments);
        return $args;
    }

    public function exchangeDelete($exchange, $if_unused, $nowait, $ticket)
    {
        $args = new AMQPWriter();
        $args->write_short($ticket)
             ->write_shortstr($exchange)
             ->write_bit($if_unused)
             ->write_bit($nowait);
        return $args;
    }

    public function queueBind($queue, $exchange, $routing_key, $nowait, $arguments, $ticket)
    {
        $args = new AMQPWriter();
        $args->write_short($ticket)
             ->write_shortstr($queue)
             ->write_shortstr($exchange)
             ->write_shortstr($routing_key)
             ->write_bit($nowait)
             ->write_table($arguments)
             ;
        return $args;
    }

    public function queueUnbind($queue, $exchange, $routing_key, $arguments, $ticket)
    {
        $args = new AMQPWriter();
        $args->write_short($ticket)
             ->write_shortstr($queue)
             ->write_shortstr($exchange)
             ->write_shortstr($routing_key)
             ->write_table($arguments)
             ;
        return $args;
    }

    public function queueDeclare($queue,
                                   $passive,
                                   $durable,
                                   $exclusive,
                                   $auto_delete,
                                   $nowait,
                                   $arguments,
                                   $ticket)
    {
        $args = new AMQPWriter();
        $args->write_short($ticket)
             ->write_shortstr($queue)
             ->write_bit($passive)
             ->write_bit($durable)
             ->write_bit($exclusive)
             ->write_bit($auto_delete)
             ->write_bit($nowait)
             ->write_table($arguments);
        return $args;
    }

    public function queueDelete($queue, $if_unused, $if_empty, $nowait, $ticket)
    {
        $args = new AMQPWriter();
        $args->write_short($ticket)
             ->write_shortstr($queue)
             ->write_bit($if_unused)
             ->write_bit($if_empty)
             ->write_bit($nowait)
             ;
        return $args;
    }

    public function queuePurge($queue, $nowait, $ticket)
    {
        $args = new AMQPWriter();
        $args->write_short($ticket)
             ->write_shortstr($queue)
             ->write_bit($nowait)
             ;
        return $args;
    }

    public function basicAck($delivery_tag, $multiple)
    {
        $args = new AMQPWriter();
        $args->write_longlong($delivery_tag)
             ->write_bit($multiple)
             ;
        return $args;
    }

    public function basicCancel($consumer_tag, $nowait)
    {
        $args = new AMQPWriter();
        $args->write_shortstr($consumer_tag)
             ->write_bit($nowait)
             ;
        return $args;
    }

    public function basicConsume($queue, $consumer_tag, $no_local,
                                  $no_ack, $exclusive, $nowait, $ticket)
    {
        $args = new AMQPWriter();
        $args->write_short($ticket)
             ->write_shortstr($queue)
             ->write_shortstr($consumer_tag)
             ->write_bit($no_local)
             ->write_bit($no_ack)
             ->write_bit($exclusive)
             ->write_bit($nowait)
             ;
        return $args;
    }

    public function basicGet($queue, $no_ack, $ticket)
    {
        $args = new AMQPWriter();
        $args->write_short($ticket)
             ->write_shortstr($queue)
             ->write_bit($no_ack)
             ;
        return $args;
    }

    public function basicPublish($exchange, $routing_key, $mandatory, $immediate, $ticket)
    {
        $args = new AMQPWriter();
        $args->write_short($ticket)
             ->write_shortstr($exchange)
             ->write_shortstr($routing_key)
             ->write_bit($mandatory)
             ->write_bit($immediate)
             ;
        return $args;
    }

    public function basicQos($prefetch_size, $prefetch_count, $a_global)
    {
        $args = new AMQPWriter();
        $args->write_long($prefetch_size)
             ->write_short($prefetch_count)
             ->write_bit($a_global)
             ;
        return $args;
    }

    public function basicRecover($requeue)
    {
        $args = new AMQPWriter();
        $args->write_bit($requeue);
        return $args;
    }

    public function basicReject($delivery_tag, $requeue)
    {
        $args = new AMQPWriter();
        $args->write_longlong($delivery_tag)
             ->write_bit($requeue)
             ;
        return $args;
    }
}
<?php

namespace PhpAmqpLib\Channel;
use PhpAmqpLib\Wire\AMQPReader;

/**
 * @link https://livebook.manning.com/book/rabbitmq-in-depth/chapter-2/v-13/22
 * @link https://www.rabbitmq.com/resources/specs/amqp0-9-1.pdf 4.2.6 Content Framing
 */
final class Frame
{
    public const FRAME_HEADER_SIZE = AMQPReader::OCTET + AMQPReader::SHORT + AMQPReader::LONG;
    public const END = 0xCE;

    public const TYPE_METHOD = 1;
    public const TYPE_HEADER = 2;
    public const TYPE_BODY = 3;
    public const TYPE_HEARTBEAT = 8;

    /** @var int */
    private $type;

    /** @var int */
    private $channel;

    /** @var int */
    private $size;

    /** @var string|null */
    private $payload;

    public function __construct(int $type, int $channel, int $size, ?string $payload = null)
    {
        $this->type = $type;
        $this->channel = $channel;
        $this->size = $size;
        $this->payload = $payload;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getChannel(): int
    {
        return $this->channel;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    public function isMethod(): bool
    {
        return $this->type === self::TYPE_METHOD;
    }

    public function isHeartbeat(): bool
    {
        return $this->type === self::TYPE_HEARTBEAT;
    }
}

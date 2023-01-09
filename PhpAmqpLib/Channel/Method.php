<?php

namespace PhpAmqpLib\Channel;

final class Method
{
    /** @var int */
    private $class;

    /** @var int */
    private $method;

    /** @var string */
    private $arguments;

    public function __construct(int $class, int $method, string $arguments)
    {
        $this->class = $class;
        $this->method = $method;
        $this->arguments = $arguments;
    }

    public function getClass(): int
    {
        return $this->class;
    }

    public function getMethod(): int
    {
        return $this->method;
    }

    public function getArguments(): string
    {
        return $this->arguments;
    }

    public function getSignature(): string
    {
        return $this->class . ',' . $this->method;
    }
}

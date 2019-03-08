<?php

namespace PhpAmqpLib\Tests\Functional;

use Httpful\Request;

class ToxiProxy
{
    /** @var string */
    private $name;

    /** @var string */
    private $api;

    /** @var string */
    private $host;

    /** @var int */
    private $listen;

    /**
     * @param string $name
     * @param string $host
     * @param int $port
     */
    public function __construct($name, $host, $port = 8474)
    {
        $this->name = $name;
        $this->host = $host;
        $this->api = 'http://' . $host . ':' . $port;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Open new proxy connection to $upstream and listen on port $port.
     * @param string $upstream
     * @param int $listen
     */
    public function open($host, $port, $listen)
    {
        $payload = array(
            'name' => $this->name,
            'upstream' => $host . ':' . $port,
            'listen' => ':' . $listen,
        );
        $url = $this->api . '/proxies';
        $request = Request::post($url, json_encode($payload), 'json');
        $request->timeout(1);
        $request->expectsJson();
        $response = $request->send();
        if ($response->code !== 201) {
            throw new \RuntimeException('Cannot create Toxiproxy connection');
        }
        $this->listen = $listen;
    }

    /**
     * Enable proxy $type manipulation.
     * @param $type One of latency, bandwidth, slow_close, timeout, slicer, limit_data
     * @param array $attributes
     * @param string $direction Either upstream or downstream.
     * @param float $toxicity
     * @see https://github.com/Shopify/toxiproxy#toxics
     */
    public function mode($type, $attributes = array(), $direction = 'upstream', $toxicity = 1.0)
    {
        $payload = [
            'name' => null,
            'stream' => $direction,
            'type' => $type,
            'toxicity' => $toxicity,
            'attributes' => !empty($attributes) ? $attributes : null,
        ];
        $url = sprintf('%s/proxies/%s/toxics', $this->api, $this->name);
        $request = Request::post($url, json_encode($payload), 'json');
        $request->timeout(1);
        $request->expectsJson();
        $response = $request->send();

        if ($response->code !== 200) {
            throw new \RuntimeException('Cannot set Toxiproxy connection mode');
        }
    }

    /**
     * Disable(block) proxy connection so no data can be transferred.
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    public function disable()
    {
        $url = sprintf('%s/proxies/%s', $this->api, $this->name);
        $response = Request::post($url, json_encode(array('enabled' => false)), 'json')->send();
        if ($response->code !== 200) {
            throw new \RuntimeException('Cannot disable Toxiproxy connection');
        }
    }

    /**
     * Completely close connection to upstream.
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    public function close()
    {
        $url = sprintf('%s/proxies/%s', $this->api, $this->name);
        $response = Request::delete($url)->send();
        if ($response->code !== 204 && $response->code !== 404) {
            throw new \RuntimeException('Cannot close Toxiproxy connection');
        }
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return int|null
     */
    public function getPort()
    {
        return $this->listen;
    }
}

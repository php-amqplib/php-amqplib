<?php

namespace PhpAmqpLib\Tests\Functional;

use GuzzleHttp\Client;
use Psr\Http\Client\ClientInterface;

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

    /** @var bool */
    private $isOpen = false;

    /** @var ClientInterface */
    private $client;

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
        $this->client = new Client(['timeout' => 1, 'http_errors' => false]);
    }

    public function __destruct()
    {
        if ($this->isOpen) {
            $this->close();
        }
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
        $response = $this->client->post($url, ['json' => $payload]);
        if ($response->getStatusCode() !== 201) {
            throw new \RuntimeException('Cannot create Toxiproxy connection');
        }
        $this->listen = $listen;
        $this->isOpen = true;
    }

    /**
     * Enable proxy $type manipulation.
     * @param string $type One of latency, bandwidth, slow_close, timeout, slicer, limit_data
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
        $response = $this->client->post($url, ['json' => $payload]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Cannot set Toxiproxy connection mode');
        }
    }

    /**
     * Disable(block) proxy connection so no data can be transferred.
     */
    public function disable()
    {
        $url = sprintf('%s/proxies/%s', $this->api, $this->name);
        $response = $this->client->post($url, ['json' => ['enabled' => false]]);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Cannot disable Toxiproxy connection');
        }
    }

    /**
     * Completely close connection to upstream.
     */
    public function close()
    {
        $url = sprintf('%s/proxies/%s', $this->api, $this->name);
        $response = $this->client->delete($url);
        if ($response->getStatusCode() !== 204 && $response->getStatusCode() !== 404) {
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

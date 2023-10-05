<?php

namespace PhpAmqpLib\Connection;

use InvalidArgumentException;
use PhpAmqpLib\Wire;

/**
 * @since 3.2.0
 */
final class AMQPConnectionConfig
{
    public const AUTH_PLAIN = 'PLAIN';
    public const AUTH_AMQPPLAIN = 'AMQPLAIN';
    public const AUTH_EXTERNAL = 'EXTERNAL';
    public const IO_TYPE_STREAM = 'stream';
    public const IO_TYPE_SOCKET = 'socket';

    /** @var string */
    private $ioType = self::IO_TYPE_STREAM;

    /** @var bool */
    private $isLazy = false;

    /** @var string */
    private $host = '127.0.0.1';

    /** @var int */
    private $port = 5672;

    /** @var string */
    private $user = 'guest';

    /** @var string */
    private $password = 'guest';

    /** @var string */
    private $vhost = '/';

    /** @var bool */
    private $insist = false;

    /** @var string */
    private $loginMethod = self::AUTH_AMQPPLAIN;

    /** @var string|null */
    private $loginResponse;

    /** @var string */
    private $locale = 'en_US';

    /** @var float */
    private $connectionTimeout = 3.0;

    /** @var float */
    private $readTimeout = 3.0;

    /** @var float */
    private $writeTimeout = 3.0;

    /** @var float */
    private $channelRPCTimeout = 0.0;

    /** @var int */
    private $heartbeat = 0;

    /** @var bool */
    private $keepalive = false;

    /** @var bool */
    private $isSecure = false;

    /**
     * @deprecated Use sslCryptoMethod
     * @var string
     */
    private $networkProtocol = 'tcp';

    /** @var resource|null */
    private $streamContext;

    /** @var int */
    private $sendBufferSize = 0;

    /** @var bool */
    private $dispatchSignals = true;

    /** @var string */
    private $amqpProtocol = Wire\Constants091::VERSION;

    /**
     * Whether to use strict AMQP0.9.1 field types. RabbitMQ does not support that.
     * @var bool
     */
    private $protocolStrictFields = false;

    /** @var string|null */
    private $sslCaCert;

    /**
     * @var string|null
     */
    private $sslCaPath;

    /** @var string|null */
    private $sslCert;

    /** @var string|null */
    private $sslKey;

    /** @var bool|null */
    private $sslVerify;

    /** @var bool|null */
    private $sslVerifyName;

    /** @var string|null */
    private $sslPassPhrase;

    /** @var string|null */
    private $sslCiphers;

    /** @var int|null */
    private $sslSecurityLevel;

    /** @var int|null */
    private $sslCryptoMethod;

    /** @var string */
    private $connectionName = '';

    /**
     * Output all networks packets for debug purposes.
     * @var bool
     */
    private $debugPackets = false;

    public function getIoType(): string
    {
        return $this->ioType;
    }

    /**
     * Set which IO type will be used, stream or socket.
     * @param string $ioType
     */
    public function setIoType(string $ioType): void
    {
        if ($ioType !== self::IO_TYPE_STREAM && $ioType !== self::IO_TYPE_SOCKET) {
            throw new InvalidArgumentException('IO type can be either "stream" or "socket"');
        }
        $this->ioType = $ioType;
    }

    public function isLazy(): bool
    {
        return $this->isLazy;
    }

    public function setIsLazy(bool $isLazy): void
    {
        $this->isLazy = $isLazy;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): void
    {
        if ($port <= 0) {
            throw new InvalidArgumentException('Port number must be greater than 0');
        }
        $this->port = $port;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getVhost(): string
    {
        return $this->vhost;
    }

    public function setVhost(string $vhost): void
    {
        self::assertStringNotEmpty($vhost, 'vhost');
        $this->vhost = $vhost;
    }

    public function isInsist(): bool
    {
        return $this->insist;
    }

    public function setInsist(bool $insist): void
    {
        $this->insist = $insist;
    }

    public function getLoginMethod(): string
    {
        return $this->loginMethod;
    }

    public function setLoginMethod(string $loginMethod): void
    {
        if (
            $loginMethod !== self::AUTH_PLAIN
            && $loginMethod !== self::AUTH_AMQPPLAIN
            && $loginMethod !== self::AUTH_EXTERNAL
        ) {
            throw new InvalidArgumentException('Unknown login method: ' . $loginMethod);
        }
        if ($loginMethod === self::AUTH_EXTERNAL && (!empty($this->user) || !empty($this->password))) {
            throw new InvalidArgumentException('External auth method cannot be used together with user credentials.');
        }
        $this->loginMethod = $loginMethod;
    }

    public function getLoginResponse(): ?string
    {
        return $this->loginResponse;
    }

    public function setLoginResponse(string $loginResponse): void
    {
        $this->loginResponse = $loginResponse;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        self::assertStringNotEmpty($locale, 'locale');
        $this->locale = $locale;
    }

    public function getConnectionTimeout(): float
    {
        return $this->connectionTimeout;
    }

    public function setConnectionTimeout(float $connectionTimeout): void
    {
        $this->connectionTimeout = $connectionTimeout;
    }

    public function getReadTimeout(): float
    {
        return $this->readTimeout;
    }

    public function setReadTimeout(float $readTimeout): void
    {
        self::assertGreaterOrEq($readTimeout, 0, 'read timeout');
        $this->readTimeout = $readTimeout;
    }

    public function getWriteTimeout(): float
    {
        return $this->writeTimeout;
    }

    public function setWriteTimeout(float $writeTimeout): void
    {
        self::assertGreaterOrEq($writeTimeout, 0, 'write timeout');
        $this->writeTimeout = $writeTimeout;
    }

    public function getChannelRPCTimeout(): float
    {
        return $this->channelRPCTimeout;
    }

    public function setChannelRPCTimeout(float $channelRPCTimeout): void
    {
        self::assertGreaterOrEq($channelRPCTimeout, 0, 'channel RPC timeout');
        $this->channelRPCTimeout = $channelRPCTimeout;
    }

    public function getHeartbeat(): int
    {
        return $this->heartbeat;
    }

    public function setHeartbeat(int $heartbeat): void
    {
        self::assertGreaterOrEq($heartbeat, 0, 'heartbeat');
        $this->heartbeat = $heartbeat;
    }

    public function isKeepalive(): bool
    {
        return $this->keepalive;
    }

    public function setKeepalive(bool $keepalive): void
    {
        $this->keepalive = $keepalive;
    }

    public function isSecure(): bool
    {
        return $this->isSecure;
    }

    public function setIsSecure(bool $isSecure): void
    {
        $this->isSecure = $isSecure;

        if ($this->isSecure) {
            $this->networkProtocol = 'tls';
            $this->sslCryptoMethod = STREAM_CRYPTO_METHOD_ANY_CLIENT;
        } else {
            $this->networkProtocol = 'tcp';
            $this->sslCryptoMethod = null;
        }
    }

    /**
     * @deprecated Use getSslCryptoMethod()
     */
    public function getNetworkProtocol(): string
    {
        return $this->networkProtocol;
    }

    /**
     * @deprecated Use setIsSecure() and setSslCryptoMethod()
     */
    public function setNetworkProtocol(string $networkProtocol): void
    {
        self::assertStringNotEmpty($networkProtocol, 'network protocol');
        $this->networkProtocol = $networkProtocol;
    }

    /**
     * @return resource|null
     */
    public function getStreamContext()
    {
        return $this->streamContext;
    }

    /**
     * @param resource|null $streamContext
     */
    public function setStreamContext($streamContext): void
    {
        if ($streamContext === null) {
            $this->streamContext = null;
            return;
        }

        if (!is_resource($streamContext) || get_resource_type($streamContext) !== 'stream-context') {
            throw new InvalidArgumentException('Resource must be valid stream context');
        }
        $this->streamContext = $streamContext;
    }

    /**
     * @return int
     * @since 3.2.1
     */
    public function getSendBufferSize(): int
    {
        return $this->sendBufferSize;
    }

    /**
     * Socket send buffer size. Set 0 to keep system default.
     * @param int $sendBufferSize
     * @return void
     * @since 3.2.1
     */
    public function setSendBufferSize(int $sendBufferSize): void
    {
        self::assertGreaterOrEq($sendBufferSize, 0, 'sendBufferSize');
        $this->sendBufferSize = $sendBufferSize;
    }

    public function isSignalsDispatchEnabled(): bool
    {
        return $this->dispatchSignals;
    }

    public function enableSignalDispatch(bool $dispatchSignals): void
    {
        $this->dispatchSignals = $dispatchSignals;
    }

    public function getAMQPProtocol(): string
    {
        return $this->amqpProtocol;
    }

    public function setAMQPProtocol(string $protocol): void
    {
        if ($protocol !== Wire\Constants091::VERSION && $protocol !== Wire\Constants080::VERSION) {
            throw new InvalidArgumentException('AMQP protocol can be either "0.9.1" or "8.0"');
        }
        $this->amqpProtocol = $protocol;
    }

    public function isProtocolStrictFieldsEnabled(): bool
    {
        return $this->protocolStrictFields;
    }

    public function setProtocolStrictFields(bool $protocolStrictFields): void
    {
        $this->protocolStrictFields = $protocolStrictFields;
    }

    public function getSslCaCert(): ?string
    {
        return $this->sslCaCert;
    }

    public function setSslCaCert(?string $sslCaCert): void
    {
        $this->sslCaCert = $sslCaCert;
    }

    public function getSslCaPath(): ?string
    {
        return $this->sslCaPath;
    }

    public function setSslCaPath(?string $sslCaPath): void
    {
        $this->sslCaPath = $sslCaPath;
    }

    public function getSslCert(): ?string
    {
        return $this->sslCert;
    }

    public function setSslCert(?string $sslCert): void
    {
        $this->sslCert = $sslCert;
    }

    public function getSslKey(): ?string
    {
        return $this->sslKey;
    }

    public function setSslKey(?string $sslKey): void
    {
        $this->sslKey = $sslKey;
    }

    public function getSslVerify(): ?bool
    {
        return $this->sslVerify;
    }

    public function setSslVerify(?bool $sslVerify): void
    {
        $this->sslVerify = $sslVerify;

        if (!$this->sslVerify) {
            $this->setSslVerifyName(false);
        }
    }

    public function getSslVerifyName(): ?bool
    {
        return $this->sslVerifyName;
    }

    public function setSslVerifyName(?bool $sslVerifyName): void
    {
        $this->sslVerifyName = $sslVerifyName;
    }

    public function getSslPassPhrase(): ?string
    {
        return $this->sslPassPhrase;
    }

    public function setSslPassPhrase(?string $sslPassPhrase): void
    {
        $this->sslPassPhrase = $sslPassPhrase;
    }

    public function getSslCiphers(): ?string
    {
        return $this->sslCiphers;
    }

    public function setSslCiphers(?string $sslCiphers): void
    {
        $this->sslCiphers = $sslCiphers;
    }

    public function getSslSecurityLevel(): ?int
    {
        return $this->sslSecurityLevel;
    }

    public function setSslSecurityLevel(?int $sslSecurityLevel): void
    {
        $this->sslSecurityLevel = $sslSecurityLevel;
    }

    public function getSslCryptoMethod(): ?int
    {
        return $this->sslCryptoMethod;
    }

    public function setSslCryptoMethod(?int $sslCryptoMethod): void
    {
        $this->sslCryptoMethod = $sslCryptoMethod;
    }

    public function isDebugPackets(): bool
    {
        return $this->debugPackets;
    }

    public function setDebugPackets(bool $debugPackets): void
    {
        $this->debugPackets = $debugPackets;
    }

    private static function assertStringNotEmpty($value, string $param): void
    {
        $value = trim($value);
        if (empty($value)) {
            throw new InvalidArgumentException(sprintf('Parameter "%s" must be non empty string', $param));
        }
    }

    /**
     * @param int|float $value
     * @param int $limit
     * @param string $param
     */
    private static function assertGreaterOrEq($value, int $limit, string $param): void
    {
        if ($value < $limit) {
            throw new InvalidArgumentException(sprintf('Parameter "%s" must be greater than zero', $param));
        }
    }

    /**
     * @return string
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * @param string $connectionName
     */
    public function setConnectionName(string $connectionName): void
    {
        $this->connectionName = $connectionName;
    }
}

<?php

namespace PhpAmqpLib\Connection;

use LogicException;

/**
 * @since 3.2.0
 */
class AMQPConnectionFactory
{
    public static function create(AMQPConnectionConfig $config): AbstractConnection
    {
        if ($config->getIoType() === AMQPConnectionConfig::IO_TYPE_STREAM) {
            if ($config->isSecure()) {
                if ($config->isLazy()) {
                    $class = AMQPLazySSLConnection::class;
                } else {
                    $class = AMQPSSLConnection::class;
                }

                $connection = new $class(
                    $config->getHost(),
                    $config->getPort(),
                    $config->getUser(),
                    $config->getPassword(),
                    $config->getVhost(),
                    self::getSslOptions($config),
                    [
                        'insist' => $config->isInsist(),
                        'login_method' => $config->getLoginMethod(),
                        'locale' => $config->getLocale(),
                        'connection_timeout' => $config->getConnectionTimeout(),
                        'read_write_timeout' => self::getReadWriteTimeout($config),
                        'keepalive' => $config->isKeepalive(),
                        'heartbeat' => $config->getHeartbeat(),
                    ],
                    $config->getNetworkProtocol(),
                    $config
                );
            } else {
                if ($config->isLazy()) {
                    $class = AMQPLazyConnection::class;
                } else {
                    $class = AMQPStreamConnection::class;
                }
                $connection = new $class(
                    $config->getHost(),
                    $config->getPort(),
                    $config->getUser(),
                    $config->getPassword(),
                    $config->getVhost(),
                    $config->isInsist(),
                    $config->getLoginMethod(),
                    null,
                    $config->getLocale(),
                    $config->getConnectionTimeout(),
                    self::getReadWriteTimeout($config),
                    $config->getStreamContext(),
                    $config->isKeepalive(),
                    $config->getHeartbeat(),
                    $config->getChannelRPCTimeout(),
                    $config->getNetworkProtocol(),
                    $config
                );
            }
        } else {
            if ($config->isSecure()) {
                throw new LogicException('The socket connection implementation does not support secure connections.');
            }

            if ($config->isLazy()) {
                $class = AMQPLazySocketConnection::class;
            } else {
                $class = AMQPSocketConnection::class;
            }
            $connection = new $class(
                $config->getHost(),
                $config->getPort(),
                $config->getUser(),
                $config->getPassword(),
                $config->getVhost(),
                $config->isInsist(),
                $config->getLoginMethod(),
                null,
                $config->getLocale(),
                $config->getReadTimeout(),
                $config->isKeepalive(),
                $config->getWriteTimeout(),
                $config->getHeartbeat(),
                $config->getChannelRPCTimeout()
            );
        }

        return $connection;
    }

    private static function getReadWriteTimeout(AMQPConnectionConfig $config): float
    {
        return min($config->getReadTimeout(), $config->getWriteTimeout());
    }

    /**
     * @param AMQPConnectionConfig $config
     * @return mixed[]
     */
    private static function getSslOptions(AMQPConnectionConfig $config): array
    {
        return array_filter([
           'cafile' => $config->getSslCaCert(),
           'local_cert' => $config->getSslCert(),
           'local_pk' => $config->getSslKey(),
           'verify_peer' => $config->getSslVerify(),
           'verify_peer_name' => $config->getSslVerifyName(),
           'passphrase' => $config->getSslPassPhrase(),
           'ciphers' => $config->getSslCiphers(),
        ], static function ($value) { return null !== $value; });
    }
}

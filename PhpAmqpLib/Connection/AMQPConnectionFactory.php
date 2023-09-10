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
                $connection = new AMQPSSLConnection(
                    $config->getHost(),
                    $config->getPort(),
                    $config->getUser(),
                    $config->getPassword(),
                    $config->getVhost(),
                    self::getSslOptions($config),
                    [
                        'insist' => $config->isInsist(),
                        'login_method' => $config->getLoginMethod(),
                        'login_response' => $config->getLoginResponse(),
                        'locale' => $config->getLocale(),
                        'connection_timeout' => $config->getConnectionTimeout(),
                        'read_write_timeout' => self::getReadWriteTimeout($config),
                        'keepalive' => $config->isKeepalive(),
                        'heartbeat' => $config->getHeartbeat(),
                    ],
                    $config
                );
            } else {
                $connection = new AMQPStreamConnection(
                    $config->getHost(),
                    $config->getPort(),
                    $config->getUser(),
                    $config->getPassword(),
                    $config->getVhost(),
                    $config->isInsist(),
                    $config->getLoginMethod(),
                    $config->getLoginResponse(),
                    $config->getLocale(),
                    $config->getConnectionTimeout(),
                    self::getReadWriteTimeout($config),
                    $config->getStreamContext(),
                    $config->isKeepalive(),
                    $config->getHeartbeat(),
                    $config->getChannelRPCTimeout(),
                    $config
                );
            }
        } else {
            if ($config->isSecure()) {
                throw new LogicException('The socket connection implementation does not support secure connections.');
            }

            $connection = new AMQPSocketConnection(
                $config->getHost(),
                $config->getPort(),
                $config->getUser(),
                $config->getPassword(),
                $config->getVhost(),
                $config->isInsist(),
                $config->getLoginMethod(),
                $config->getLoginResponse(),
                $config->getLocale(),
                $config->getReadTimeout(),
                $config->isKeepalive(),
                $config->getWriteTimeout(),
                $config->getHeartbeat(),
                $config->getChannelRPCTimeout(),
                $config
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
            'capath' => $config->getSslCaPath(),
            'local_cert' => $config->getSslCert(),
            'local_pk' => $config->getSslKey(),
            'verify_peer' => $config->getSslVerify(),
            'verify_peer_name' => $config->getSslVerifyName(),
            'passphrase' => $config->getSslPassPhrase(),
            'ciphers' => $config->getSslCiphers(),
            'security_level' => $config->getSslSecurityLevel(),
            'crypto_method' => $config->getSslCryptoMethod(),
        ], static function ($value) {
            return null !== $value;
        });
    }
}

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
                self::getStreamContext($config),
                $config->isKeepalive(),
                $config->getHeartbeat(),
                $config->getChannelRPCTimeout(),
                $config
            );
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
     * @return string[]
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

    /**
     * @param AMQPConnectionConfig $config
     * @return resource|null
     */
    private static function getStreamContext(AMQPConnectionConfig $config)
    {
        $context = $config->getStreamContext();

        if ($config->isSecure()) {
            if (!$context) {
                $context = stream_context_create();
            }
            $options = self::getSslOptions($config);
            foreach ($options as $k => $v) {
                // Note: 'ssl' applies to 'tls' as well
                // https://www.php.net/manual/en/context.ssl.php
                stream_context_set_option($context, 'ssl', $k, $v);
            }
        }

        return $context;
    }
}

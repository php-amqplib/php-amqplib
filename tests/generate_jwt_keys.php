<?php

// if not set, generates just two different keys, both for about 2 hours
define('JWT_TOKEN_1', getenv('TEST_RABBITMQ_JWT_TOKEN_1') ? getenv('TEST_RABBITMQ_JWT_TOKEN_1') : generateJwtToken(7200));
define('JWT_TOKEN_2', getenv('TEST_RABBITMQ_JWT_TOKEN_2') ? getenv('TEST_RABBITMQ_JWT_TOKEN_2') : generateJwtToken(7205));

function generateJwtToken($expire = 7200)
{
    $header = jwt_generator_base64url_encode(json_encode(array(
        'typ' => 'JWT',
        'alg' => 'RS256',
    )));

    $payload = jwt_generator_base64url_encode(json_encode(array(
        'aud' => 'amqplib-test', // must match rabbitmq.conf/auth_oauth2.resource_server_id
        'sub' => 'username',
        'scope' => array(
            'rabbitmq.write:*/*/*',
            'rabbitmq.read:*/*/*',
            'rabbitmq.configure:*/*/*'
        ),
        'iat' => time(),
        'exp' => time() + $expire
    )));

    $private_key = openssl_pkey_get_private("file://" . __DIR__ . "/certs/client_key.pem");

    openssl_sign(
        "$header.$payload",
        $signature,
        $private_key,
        "sha256WithRSAEncryption"
    );

    openssl_free_key($private_key);

    $signature64 = jwt_generator_base64url_encode($signature);

    return "$header.$payload.$signature64";
}

function jwt_generator_base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

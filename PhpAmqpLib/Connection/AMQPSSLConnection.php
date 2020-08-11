<?php
namespace PhpAmqpLib\Connection;

class AMQPSSLConnection extends AMQPStreamConnection
{
    /**
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     * @param string $vhost
     * @param array $ssl_options
     * @param array $options
     * @param string $ssl_protocol
     */
    public function __construct(
        $host,
        $port,
        $user,
        $password,
        $vhost = '/',
        $ssl_options = array(),
        $options = array(),
        $ssl_protocol = 'ssl'
    ) {
        $ssl_context = empty($ssl_options) ? null : $this->create_ssl_context($ssl_options);
        parent::__construct(
            $host,
            $port,
            $user,
            $password,
            $vhost,
            isset($options['insist']) ? $options['insist'] : false,
            isset($options['login_method']) ? $options['login_method'] : 'AMQPLAIN',
            isset($options['login_response']) ? $options['login_response'] : null,
            isset($options['locale']) ? $options['locale'] : 'en_US',
            isset($options['connection_timeout']) ? $options['connection_timeout'] : 3,
            isset($options['read_write_timeout']) ? $options['read_write_timeout'] : 130,
            $ssl_context,
            isset($options['keepalive']) ? $options['keepalive'] : false,
            isset($options['heartbeat']) ? $options['heartbeat'] : 0,
            isset($options['channel_rpc_timeout']) ? $options['channel_rpc_timeout'] : 0.0,
            $ssl_protocol
        );
    }

    public static function try_create_connection($host, $port, $user, $password, $vhost, $options) {
        $ssl_options = isset($options['ssl_options']) ? $options['ssl_options'] : [];
        return new static($host, $port, $user, $password, $vhost, $ssl_options, $options);
    }

    /**
     * @param array $options
     * @return resource
     */
    private function create_ssl_context($options)
    {
        $ssl_context = stream_context_create();
        foreach ($options as $k => $v) {
            stream_context_set_option($ssl_context, 'ssl', $k, $v);
        }

        return $ssl_context;
    }
}

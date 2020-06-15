<?php

namespace PhpAmqpLib\Connection;

use PhpAmqpLib\Channel\AMQPChannelInterface;
use PhpAmqpLib\Wire\AMQPClientInterface;
use PhpAmqpLib\Wire\AMQPWriter;
use PhpAmqpLib\Wire\IO\IOInterface;

/**
 * AMQPConnectionInterface Interface
 */
interface AMQPConnectionInterface
{
    /**
     * Reconnects using the original connection settings.
     * This will not recreate any channels that were established previously
     */
    public function reconnect();

    /**
     * Cloning will use the old properties to make a new connection to the same server
     */
    public function __clone();

    public function __destruct();

    /**
     * @param int $sec
     * @param int $usec
     * @return mixed
     */
    public function select($sec, $usec = 0);

    /**
     * Allows to not close the connection
     * it's useful after the fork when you don't want to close parent process connection
     *
     * @param bool $close
     */
    public function set_close_on_destruct($close = true);

    /**
     * @param string $data
     */
    public function write($data);

    /**
     * @return int
     */
    public function get_free_channel_id();

    /**
     * @param string $channel
     * @param int $class_id
     * @param int $weight
     * @param int $body_size
     * @param string $packed_properties
     * @param string $body
     * @param AMQPClientInterface $pkt
     */
    public function send_content($channel, $class_id, $weight, $body_size, $packed_properties, $body, $pkt);

    /**
     * Returns a new AMQPWriter or mutates the provided $pkt
     *
     * @param string $channel
     * @param int $class_id
     * @param int $weight
     * @param int $body_size
     * @param string $packed_properties
     * @param string $body
     * @param AMQPWriter $pkt
     * @return AMQPClientInterface
     */
    public function prepare_content($channel, $class_id, $weight, $body_size, $packed_properties, $body, $pkt);

    /**
     * Fetches a channel object identified by the numeric channel_id, or
     * create that object if it doesn't already exist.
     *
     * @param int $channel_id
     * @return AMQPChannelInterface
     */
    public function channel($channel_id = null);

    /**
     * Requests a connection close
     *
     * @param int $reply_code
     * @param string $reply_text
     * @param array $method_sig
     * @return mixed|null
     */
    public function close($reply_code = 0, $reply_text = '', $method_sig = array(0, 0));

    /**
     * @return resource
     * @deprecated No direct access to communication socket should be available.
     */
    public function getSocket();

    /**
     * @return IOInterface
     * @deprecated
     */
    public function getIO();

    /**
     * Check connection heartbeat if enabled.
     */
    public function checkHeartBeat();

    /**
     * Sets a handler which is called whenever a connection.block is sent from the server
     *
     * @param callable $callback
     */
    public function set_connection_block_handler($callback);

    /**
     * Sets a handler which is called whenever a connection.block is sent from the server
     *
     * @param callable $callback
     */
    public function set_connection_unblock_handler($callback);

    /**
     * Gets the connection status
     *
     * @return bool
     */
    public function isConnected();

    /**
     * Get the connection blocked state.
     *
     * @return bool
     * @since v2.12.0
     */
    public function isBlocked();

    /**
     * Should the connection be attempted during construction?
     *
     * @return bool
     */
    public function connectOnConstruct();

    /**
     * @return array
     */
    public function getServerProperties();

    /**
     * Get the library properties for populating the client protocol information
     *
     * @return array
     */
    public function getLibraryProperties();

    /**
     * Create a connection
     *
     * @param array $hosts
     * @param array $options
     *
     * @return mixed
     * @throws \Exception
     */
    public static function create_connection($hosts, $options = array());

    /**
     * Validate the host
     *
     * @param $host
     * @return mixed
     */
    public static function validate_host($host);

}

<?php

namespace PhpAmqpLib\Connection;

use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
use PhpAmqpLib\Exception\AMQPInvalidFrameException;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPNoDataException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPSocketException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Helper\Assert;
use PhpAmqpLib\Package;
use PhpAmqpLib\Wire;
use PhpAmqpLib\Wire\AMQPReader;
use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Wire\AMQPWriter;
use PhpAmqpLib\Wire\IO\AbstractIO;

abstract class AbstractConnection extends AbstractChannel
{
    /**
     * @var array
     * @internal
     */
    public static $LIBRARY_PROPERTIES = array(
        'product' => array('S', Package::NAME),
        'platform' => array('S', 'PHP'),
        'version' => array('S', Package::VERSION),
        'information' => array('S', ''),
        'copyright' => array('S', ''),
        'capabilities' => array(
            'F',
            array(
                'authentication_failure_close' => array('t', true),
                'publisher_confirms' => array('t', true),
                'consumer_cancel_notify' => array('t', true),
                'exchange_exchange_bindings' => array('t', true),
                'basic.nack' => array('t', true),
                'connection.blocked' => array('t', true)
            )
        )
    );

    /**
     * @var AMQPChannel[]
     * @internal
     */
    public $channels = array();

    /** @var int */
    protected $versionMajor;

    /** @var int */
    protected $versionMinor;

    /** @var array */
    protected $serverProperties;

    /** @var array */
    protected $mechanisms;

    /** @var array */
    protected $locales;

    /** @var bool */
    protected $waitTuneOk;

    /** @var string */
    protected $knownHosts;

    /** @var null|AMQPReader */
    protected $input;

    /** @var string */
    protected $vhost;

    /** @var bool */
    protected $insist;

    /** @var string */
    protected $loginMethod;

    /**
     * @var null|string
     * @deprecated
     */
    protected $loginResponse;

    /** @var string */
    protected $locale;

    /** @var int */
    protected $heartbeat;

    /** @var float */
    protected $lastFrame;

    /** @var int */
    protected $channelMax = 65535;

    /** @var int */
    protected $frameMax = 131072;

     /** @var array Constructor parameters for clone */
    protected $constructParams;

    /** @var bool Close the connection in destructor */
    protected $closeOnDestruct = true;

    /** @var bool Maintain connection status */
    protected $isConnected = false;

    /** @var \PhpAmqpLib\Wire\IO\AbstractIO */
    protected $io;

    /** @var \PhpAmqpLib\Wire\AMQPReader */
    protected $waitFrameReader;

    /** @var callable Handles connection blocking from the server */
    private $connectionBlockHandler;

    /** @var callable Handles connection unblocking from the server */
    private $connectionUnblockHandler;

    /** @var int Connection timeout value*/
    protected $connectionTimeout;

    /**
     * Circular buffer to speed up prepare_content().
     * Max size limited by $prepare_content_cache_max_size.
     *
     * @var array
     * @see prepareContent()
     */
    private $prepare_content_cache = array();

    /** @var int Maximal size of $prepare_content_cache */
    private $prepare_content_cache_max_size = 100;

    /**
     * Maximum time to wait for channel operations, in seconds
     * @var float $channelRpcTimeout
     */
    private $channelRpcTimeout;

    /**
     * If connection is blocked due to the broker running low on resources.
     * @var bool
     */
    protected $blocked = false;

    /**
     * If a frame is currently being written
     * @var bool
     */
    protected $writing = false;

    /**
     * @param string $user
     * @param string $password
     * @param string $vhost
     * @param bool $insist
     * @param string $loginMethod
     * @param null $loginResponse @deprecated
     * @param string $locale
     * @param AbstractIO $io
     * @param int $heartbeat
     * @param int $connectionTimeout
     * @param float $channelRpcTimeout
     * @throws \Exception
     */
    public function __construct(
        $user,
        $password,
        $vhost = '/',
        $insist = false,
        $loginMethod = 'AMQPLAIN',
        $loginResponse = null,
        $locale = 'en_US',
        AbstractIO $io,
        $heartbeat = 0,
        $connectionTimeout = 0,
        $channelRpcTimeout = 0.0
    ) {
        // save the params for the use of __clone
        $this->constructParams = func_get_args();

        $this->waitFrameReader = new AMQPReader(null);
        $this->vhost = $vhost;
        $this->insist = $insist;
        $this->loginMethod = $loginMethod;
        $this->locale = $locale;
        $this->io = $io;
        $this->heartbeat = $heartbeat;
        $this->connectionTimeout = $connectionTimeout;
        $this->channelRpcTimeout = $channelRpcTimeout;

        if ($user && $password) {
            if ($loginMethod === 'PLAIN') {
                $this->loginResponse = sprintf("\0%s\0%s", $user, $password);
            } elseif ($loginMethod === 'AMQPLAIN') {
                $loginResponse = new AMQPWriter();
                $loginResponse->writeTable(array(
                    'LOGIN' => array('S', $user),
                    'PASSWORD' => array('S', $password)
                ));

                // Skip the length
                $responseValue = $loginResponse->getvalue();
                $this->loginResponse = mb_substr($responseValue, 4, mb_strlen($responseValue, 'ASCII') - 4, 'ASCII');
            } else {
                throw new \InvalidArgumentException('Unknown login method: ' . $loginMethod);
            }
        } else {
            $this->loginResponse = null;
        }

        // Lazy Connection waits on connecting
        if ($this->connectOnConstruct()) {
            $this->connect();
        }
    }

    /**
     * Connects to the AMQP server
     */
    protected function connect()
    {
        $this->blocked = false;
        try {
            // Loop until we connect
            while (!$this->isConnected()) {
                // Assume we will connect, until we dont
                $this->setIsConnected(true);

                // Connect the socket
                $this->io->connect();

                $this->channels = array();
                // The connection object itself is treated as channel 0
                parent::__construct($this, 0);

                $this->input = new AMQPReader(null, $this->io);

                $this->write($this->constants->getHeader());
                // assume frame was sent successfully, used in $this->wait_channel()
                $this->lastFrame = microtime(true);
                $this->wait(array($this->waitHelper->getWait('connection.start')), false, $this->connectionTimeout);
                $this->xStartOk(
                    $this->getLibraryProperties(),
                    $this->loginMethod,
                    $this->loginResponse,
                    $this->locale
                );

                $this->waitTuneOk = true;
                while ($this->waitTuneOk) {
                    $this->wait(array(
                        $this->waitHelper->getWait('connection.secure'),
                        $this->waitHelper->getWait('connection.tune')
                    ), false, $this->connectionTimeout);
                }

                $host = $this->xOpen($this->vhost, '', $this->insist);
                if (!$host) {
                    //Reconnected
                    $this->io->reenableHeartbeat();
                    return null; // we weren't redirected
                }

                $this->setIsConnected(false);
                $this->closeChannels();

                // we were redirected, close the socket, loop and try again
                $this->closeSocket();
            }
        } catch (\Exception $e) {
            // Something went wrong, set the connection status
            $this->setIsConnected(false);
            $this->closeChannels();
            $this->closeInput();
            $this->closeSocket();
            throw $e; // Rethrow exception
        }
    }

    /**
     * Reconnects using the original connection settings.
     * This will not recreate any channels that were established previously
     */
    public function reconnect()
    {
        // Try to close the AMQP connection
        $this->safeClose();
        // Reconnect the socket/stream then AMQP
        $this->io->close();
        // getIO can initiate the connection setting via LazyConnection, set it here to be sure
        $this->setIsConnected(false);
        $this->connect();
    }

    /**
     * Cloning will use the old properties to make a new connection to the same server
     */
    public function __clone()
    {
        call_user_func_array(array($this, '__construct'), $this->constructParams);
    }

    public function __destruct()
    {
        if ($this->closeOnDestruct) {
            $this->safeClose();
        }
    }

    /**
     * Attempts to close the connection safely
     */
    protected function safeClose()
    {
        try {
            if (null !== $this->input) {
                $this->close();
            }
        } catch (\Exception $e) {
            // Nothing here
        }
    }

    /**
     * @param int $sec
     * @param int $usec
     * @return mixed
     */
    public function select($sec, $usec = 0)
    {
        try {
            return $this->io->select($sec, $usec);
        } catch (AMQPConnectionClosedException $e) {
            $this->doClose();
            throw $e;
        } catch (AMQPRuntimeException $e) {
            $this->setIsConnected(false);
            throw $e;
        }
    }

    /**
     * Allows to not close the connection
     * it's useful after the fork when you don't want to close parent process connection
     *
     * @param bool $close
     */
    public function setCloseOnDestruct($close = true)
    {
        $this->closeOnDestruct = (bool) $close;
    }

    protected function closeInput()
    {
        $this->debug && $this->debug->debugMsg('closing input');

        if (null !== $this->input) {
            $this->input->close();
            $this->input = null;
        }
    }

    protected function closeSocket()
    {
        $this->debug && $this->debug->debugMsg('closing socket');
        $this->io->close();
    }

    /**
     * @param string $data
     */
    public function write($data)
    {
        $this->debug->debugHexdump($data);

        try {
            $this->writing = true;
            $this->io->write($data);
        } catch (AMQPConnectionClosedException $e) {
            $this->doClose();
            throw $e;
        } catch (AMQPRuntimeException $e) {
            $this->setIsConnected(false);
            throw $e;
        } finally {
            $this->writing = false;
        }
    }

    protected function doClose()
    {
        $this->frameQueue = [];
        $this->methodQueue = [];
        $this->setIsConnected(false);
        $this->closeInput();
        $this->closeSocket();
    }

    /**
     * @return int
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function getFreeChannelId()
    {
        for ($i = 1; $i <= $this->channelMax; $i++) {
            if (!isset($this->channels[$i])) {
                return $i;
            }
        }

        throw new AMQPRuntimeException('No free channel ids');
    }

    /**
     * @param string $channel
     * @param int $classId
     * @param int $weight
     * @param int $body_size
     * @param string $packed_properties
     * @param string $body
     * @param AMQPWriter $pkt
     */
    public function sendContent($channel, $classId, $weight, $body_size, $packed_properties, $body, $pkt)
    {
        $this->prepareContent($channel, $classId, $weight, $body_size, $packed_properties, $body, $pkt);
        $this->write($pkt->getvalue());
    }

    /**
     * Returns a new AMQPWriter or mutates the provided $pkt
     *
     * @param string $channel
     * @param int $classId
     * @param int $weight
     * @param int $body_size
     * @param string $packed_properties
     * @param string $body
     * @param AMQPWriter $pkt
     * @return AMQPWriter
     */
    public function prepareContent($channel, $classId, $weight, $body_size, $packed_properties, $body, $pkt)
    {
        $pkt = $pkt ?: new AMQPWriter();

        // Content already prepared ?
        $key_cache = sprintf(
            '%s|%s|%s|%s',
            $channel,
            $packed_properties,
            $classId,
            $weight
        );

        if (!isset($this->prepare_content_cache[$key_cache])) {
            $w = new AMQPWriter();
            $w->writeOctet(2);
            $w->writeShort($channel);
            $w->writeLong(mb_strlen($packed_properties, 'ASCII') + 12);
            $w->writeShort($classId);
            $w->writeShort($weight);
            $this->prepare_content_cache[$key_cache] = $w->getvalue();
            if (count($this->prepare_content_cache) > $this->prepare_content_cache_max_size) {
                reset($this->prepare_content_cache);
                $oldKey = key($this->prepare_content_cache);
                unset($this->prepare_content_cache[$oldKey]);
            }
        }
        $pkt->write($this->prepare_content_cache[$key_cache]);

        $pkt->writeLonglong($body_size);
        $pkt->write($packed_properties);

        $pkt->writeOctet(0xCE);


        // memory efficiency: walk the string instead of biting
        // it. good for very large packets (close in size to
        // memory_limit setting)
        $position = 0;
        $bodyLength = mb_strlen($body, 'ASCII');
        while ($position < $bodyLength) {
            $payload = mb_substr($body, $position, $this->frameMax - 8, 'ASCII');
            $position += $this->frameMax - 8;

            $pkt->writeOctet(3);
            $pkt->writeShort($channel);
            $pkt->writeLong(mb_strlen($payload, 'ASCII'));

            $pkt->write($payload);

            $pkt->writeOctet(0xCE);
        }

        return $pkt;
    }

    /**
     * @param string $channel
     * @param array $methodSig
     * @param AMQPWriter|string $args
     * @param null $pkt
     */
    protected function sendChannelMethodFrame($channel, $methodSig, $args = '', $pkt = null)
    {
        $pkt = $this->prepareChannelMethodFrame($channel, $methodSig, $args, $pkt);
        $this->write($pkt->getvalue());
        $this->debug->debugMethodSignature1($methodSig);
    }

    /**
     * Returns a new AMQPWriter or mutates the provided $pkt
     *
     * @param string $channel
     * @param array $methodSig
     * @param AMQPWriter|string $args
     * @param AMQPWriter $pkt
     * @return AMQPWriter
     */
    protected function prepareChannelMethodFrame($channel, $methodSig, $args = '', $pkt = null)
    {
        if ($args instanceof AMQPWriter) {
            $args = $args->getvalue();
        }

        $pkt = $pkt ?: new AMQPWriter();

        $pkt->writeOctet(1);
        $pkt->writeShort($channel);
        $pkt->writeLong(mb_strlen($args, 'ASCII') + 4); // 4 = length of class_id and method_id
        // in payload

        $pkt->writeShort($methodSig[0]); // class_id
        $pkt->writeShort($methodSig[1]); // method_id
        $pkt->write($args);

        $pkt->writeOctet(0xCE);

        $this->debug->debugMethodSignature1($methodSig);

        return $pkt;
    }

    /**
     * Waits for a frame from the server
     *
     * @param int|float|null $timeout
     * @return array
     * @throws \Exception
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    protected function waitFrame($timeout = 0)
    {
        if (null === $this->input) {
            $this->setIsConnected(false);
            throw new AMQPConnectionClosedException('Broken pipe or closed connection');
        }

        $currentTimeout = $this->input->getTimeout();
        $this->input->setTimeout($timeout);

        try {
            // frame_type + channel_id + size
            $this->waitFrameReader->reuse(
                $this->input->read(AMQPReader::OCTET + AMQPReader::SHORT + AMQPReader::LONG)
            );

            $frameType = $this->waitFrameReader->readOctet();
            if (!$this->constants->isFrameType($frameType)) {
                throw new AMQPInvalidFrameException('Invalid frame type ' . $frameType);
            }
            $channel = $this->waitFrameReader->readShort();
            $size = $this->waitFrameReader->readLong();

            // payload + ch
            $this->waitFrameReader->reuse($this->input->read(AMQPReader::OCTET + (int) $size));

            $payload = $this->waitFrameReader->read($size);
            $ch = $this->waitFrameReader->readOctet();
        } catch (AMQPTimeoutException $e) {
            if ($this->input) {
                $this->input->setTimeout($currentTimeout);
            }
            throw $e;
        } catch (AMQPNoDataException $e) {
            if ($this->input) {
                $this->input->setTimeout($currentTimeout);
            }
            throw $e;
        } catch (AMQPConnectionClosedException $exception) {
            $this->doClose();
            throw $exception;
        }

        $this->input->setTimeout($currentTimeout);

        if ($ch != 0xCE) {
            throw new AMQPInvalidFrameException(sprintf(
                'Framing error, unexpected byte: %x',
                $ch
            ));
        }

        return array($frameType, $channel, $payload);
    }

    /**
     * Waits for a frame from the server destined for a particular channel.
     *
     * @param string $channelId
     * @param int|float|null $timeout
     * @return array
     */
    protected function waitChannel($channelId, $timeout = 0)
    {
        // Keeping the original timeout unchanged.
        $timeout = $timeout;
        while (true) {
            $start = microtime(true);
            try {
                list($frameType, $frameChannel, $payload) = $this->waitFrame($timeout);
            } catch (AMQPTimeoutException $e) {
                if (
                    $this->heartbeat && $this->lastFrame
                    && microtime(true) - ($this->heartbeat * 2) > $this->lastFrame
                ) {
                    $this->debug->debugMsg("missed server heartbeat (at threshold * 2)");
                    $this->setIsConnected(false);
                    throw new AMQPHeartbeatMissedException("Missed server heartbeat");
                }

                throw $e;
            }

            $this->lastFrame = microtime(true);

            if ($frameChannel === 0 && $frameType === 8) {
                // skip heartbeat frames and reduce the timeout by the time passed
                $this->debug->debugMsg("received server heartbeat");
                if ($timeout > 0) {
                    $timeout -= $this->lastFrame - $start;
                    if ($timeout <= 0) {
                        // If timeout has been reached, throw the exception without calling wait_frame
                        throw new AMQPTimeoutException("Timeout waiting on channel");
                    }
                }
                continue;
            }

            if ($frameChannel == $channelId) {
                return array($frameType, $payload);
            }

            // Not the channel we were looking for.  Queue this frame
            //for later, when the other channel is looking for frames.
            // Make sure the channel still exists, it could have been
            // closed by a previous Exception.
            if (isset($this->channels[$frameChannel])) {
                array_push($this->channels[$frameChannel]->frameQueue, [$frameType, $payload]);
            }

            // If we just queued up a method for channel 0 (the Connection
            // itself) it's probably a close method in reaction to some
            // error, so deal with it right away.
            if ($frameType === 1 && $frameChannel === 0) {
                $this->wait();
            }
        }
    }

    /**
     * Fetches a channel object identified by the numeric channel_id, or
     * create that object if it doesn't already exist.
     *
     * @param int $channelId
     * @return AMQPChannel
     */
    public function channel($channelId = null)
    {
        if (isset($this->channels[$channelId])) {
            return $this->channels[$channelId];
        }

        $channelId = $channelId ? $channelId : $this->getFreeChannelId();
        $ch = new AMQPChannel($this, $channelId, true, $this->channelRpcTimeout);
        $this->channels[$channelId] = $ch;

        return $ch;
    }

    /**
     * Requests a connection close
     *
     * @param int $replyCode
     * @param string $replyText
     * @param array $methodSig
     * @return mixed|null
     */
    public function close($replyCode = 0, $replyText = '', $methodSig = array(0, 0))
    {
        $result = null;
        $this->io->disableHeartbeat();
        if (empty($this->protocolWriter) || !$this->isConnected()) {
            return $result;
        }

        try {
            $this->closeChannels();
            list($classId, $methodId, $args) = $this->protocolWriter->connectionClose(
                $replyCode,
                $replyText,
                $methodSig[0],
                $methodSig[1]
            );
            $this->sendMethodFrame(array($classId, $methodId), $args);
            $result = $this->wait(
                array($this->waitHelper->getWait('connection.close_ok')),
                false,
                $this->connectionTimeout
            );
        } catch (\Exception $exception) {
            $this->doClose();
            throw $exception;
        }

        $this->setIsConnected(false);

        return $result;
    }

    /**
     * @param AMQPReader $reader
     * @throws AMQPConnectionClosedException
     */
    protected function connectionClose(AMQPReader $reader)
    {
        $code = (int)$reader->readShort();
        $reason = $reader->readShortstr();
        $class = $reader->readShort();
        $method = $reader->readShort();
        $reason .= sprintf('(%s, %s)', $class, $method);

        $this->xCloseOk();

        throw new AMQPConnectionClosedException($reason, $code);
    }

    /**
     * Confirms a connection close
     */
    protected function xCloseOk()
    {
        $this->sendMethodFrame(
            explode(',', $this->waitHelper->getWait('connection.close_ok'))
        );
        $this->doClose();
    }

    /**
     * Confirm a connection close
     */
    protected function connectionCloseOk()
    {
        $this->doClose();
    }

    /**
     * @param string $virtualHost
     * @param string $capabilities
     * @param bool $insist
     * @return mixed
     */
    protected function xOpen($virtualHost, $capabilities = '', $insist = false)
    {
        $args = new AMQPWriter();
        $args->writeShortstr($virtualHost);
        $args->writeShortstr($capabilities);
        $args->writeBits(array($insist));
        $this->sendMethodFrame(array(10, 40), $args);

        $wait = array(
            $this->waitHelper->getWait('connection.open_ok')
        );

        if ($this->protocolVersion === Wire\Constants080::VERSION) {
            $wait[] = $this->waitHelper->getWait('connection.redirect');
        }

        return $this->wait($wait, false, $this->connectionTimeout);
    }

    /**
     * Signals that the connection is ready
     *
     * @param AMQPReader $args
     */
    protected function connectionOpenOk($args)
    {
        $this->knownHosts = $args->readShortstr();
        $this->debug->debugMsg('Open OK! knownHosts: ' . $this->knownHosts);
    }

    /**
     * Asks the client to use a different server
     *
     * @param AMQPReader $args
     * @return string
     */
    protected function connectionRedirect($args)
    {
        $host = $args->readShortstr();
        $this->knownHosts = $args->readShortstr();
        $this->debug->debugMsg(sprintf(
            'Redirected to [%s], knownHosts [%s]',
            $host,
            $this->knownHosts
        ));

        return $host;
    }

    /**
     * Security mechanism challenge
     *
     * @param AMQPReader $args
     */
    protected function connectionSecure($args)
    {
        $args->readLongstr();
    }

    /**
     * Security mechanism response
     *
     * @param string $response
     */
    protected function xSecureOk($response)
    {
        $args = new AMQPWriter();
        $args->writeLongstr($response);
        $this->sendMethodFrame(array(10, 21), $args);
    }

    /**
     * Starts connection negotiation
     *
     * @param AMQPReader $args
     */
    protected function connectionStart($args)
    {
        $this->versionMajor = $args->readOctet();
        $this->versionMinor = $args->readOctet();
        $this->serverProperties = $args->readTable();
        $this->mechanisms = explode(' ', $args->readLongstr());
        $this->locales = explode(' ', $args->readLongstr());

        $this->debug->debugConnectionStart(
            $this->versionMajor,
            $this->versionMinor,
            $this->serverProperties,
            $this->mechanisms,
            $this->locales
        );
    }

    /**
     * @param AMQPTable|array $clientProperties
     * @param string $mechanism
     * @param string $response
     * @param string $locale
     */
    protected function xStartOk($clientProperties, $mechanism, $response, $locale)
    {
        $args = new AMQPWriter();
        $args->writeTable($clientProperties);
        $args->writeShortstr($mechanism);
        $args->writeLongstr($response);
        $args->writeShortstr($locale);
        $this->sendMethodFrame(array(10, 11), $args);
    }

    /**
     * Proposes connection tuning parameters
     *
     * @param AMQPReader $args
     */
    protected function connectionTune($args)
    {
        $v = $args->readShort();
        if ($v) {
            $this->channelMax = $v;
        }

        $v = $args->readLong();
        if ($v) {
            $this->frameMax = (int)$v;
        }

        // use server proposed value if not set
        if ($this->heartbeat === null) {
            $this->heartbeat = $args->readShort();
        }

        $this->xTuneOk($this->channelMax, $this->frameMax, $this->heartbeat);
    }

    /**
     * Negotiates connection tuning parameters
     *
     * @param int $channelMax
     * @param int $frameMax
     * @param int $heartbeat
     */
    protected function xTuneOk($channelMax, $frameMax, $heartbeat)
    {
        $args = new AMQPWriter();
        $args->writeShort($channelMax);
        $args->writeLong($frameMax);
        $args->writeShort($heartbeat);
        $this->sendMethodFrame(array(10, 31), $args);
        $this->waitTuneOk = false;
    }

    /**
     * @return resource
     * @deprecated No direct access to communication socket should be available.
     */
    public function getSocket()
    {
        return $this->io->getSocket();
    }

    /**
     * @return \PhpAmqpLib\Wire\IO\AbstractIO
     * @deprecated
     */
    public function getIO()
    {
        return $this->io;
    }

    /**
     * Check connection heartbeat if enabled.
     * @throws AMQPHeartbeatMissedException If too much time passed since last connection activity.
     * @throws AMQPConnectionClosedException If connection was closed due to network issues or timeouts.
     * @throws AMQPSocketException If connection was already closed.
     * @throws AMQPTimeoutException If heartbeat write takes too much time.
     * @throws AMQPIOException If other connection problems occurred.
     */
    public function checkHeartBeat()
    {
        $this->io->checkHeartbeat();
    }

    /**
     * @return float|int
     */
    public function getLastActivity()
    {
        return $this->io->getLastActivity();
    }

    /**
     * Handles connection blocked notifications
     *
     * @param AMQPReader $args
     */
    protected function connectionBlocked(AMQPReader $args)
    {
        $this->blocked = true;
        // Call the block handler and pass in the reason
        $this->dispatchToHandler($this->connectionBlockHandler, array($args->readShortstr()));
    }

    /**
     * Handles connection unblocked notifications
     */
    protected function connectionUnblocked()
    {
        $this->blocked = false;
        // No args to an unblock event
        $this->dispatchToHandler($this->connectionUnblockHandler);
    }

    /**
     * Sets a handler which is called whenever a connection.block is sent from the server
     *
     * @param callable $callback
     * @throws \InvalidArgumentException if $callback is not callable
     */
    public function setConnectionBlockHandler($callback)
    {
        Assert::isCallable($callback);
        $this->connectionBlockHandler = $callback;
    }

    /**
     * Sets a handler which is called whenever a connection.block is sent from the server
     *
     * @param callable $callback
     * @throws \InvalidArgumentException if $callback is not callable
     */
    public function setConnectionUnblockHandler($callback)
    {
        Assert::isCallable($callback);
        $this->connectionUnblockHandler = $callback;
    }

    /**
     * Gets the connection status
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->isConnected;
    }

    /**
     * Get the connection blocked state.
     * @return bool
     * @since v2.12.0
     */
    public function isBlocked()
    {
        return $this->blocked;
    }

    /**
     * Get the io writing state.
     * @return bool
     */
    public function isWriting()
    {
        return $this->writing;
    }

    /**
     * Set the connection status
     *
     * @param bool $isConnected
     */
    protected function setIsConnected($isConnected)
    {
        $this->isConnected = (bool) $isConnected;
    }

    /**
     * Closes all available channels
     */
    protected function closeChannels()
    {
        foreach ($this->channels as $key => $channel) {
            // channels[0] is this connection object, so don't close it yet
            if ($key === 0) {
                continue;
            }
            try {
                $channel->close();
            } catch (\Exception $e) {
                /* Ignore closing errors */
            }
        }
    }

    /**
     * Should the connection be attempted during construction?
     *
     * @return bool
     */
    public function connectOnConstruct()
    {
        return true;
    }

    /**
     * @return array
     */
    public function getServerProperties()
    {
        return $this->serverProperties;
    }

    /**
     * @return int
     */
    public function getHeartbeat()
    {
        return $this->heartbeat;
    }

    /**
     * Get the library properties for populating the client protocol information
     *
     * @return array
     */
    public function getLibraryProperties()
    {
        return self::$LIBRARY_PROPERTIES;
    }

    /**
     * @param array $hosts
     * @param array $options
     *
     * @return mixed
     * @throws \Exception
     */
    public static function createConnection($hosts, $options = array())
    {
        if (!is_array($hosts) || count($hosts) < 1) {
            throw new \InvalidArgumentException(
                'An array of hosts are required when attempting to create a connection'
            );
        }

        foreach ($hosts as $hostdef) {
            AbstractConnection::validateHost($hostdef);
            $host = $hostdef['host'];
            $port = $hostdef['port'];
            $user = $hostdef['user'];
            $password = $hostdef['password'];
            $vhost = isset($hostdef['vhost']) ? $hostdef['vhost'] : "/";
            try {
                $conn = static::tryCreateConnection($host, $port, $user, $password, $vhost, $options);
                return $conn;
            } catch (\Exception $e) {
                $latestException = $e;
            }
        }
        throw $latestException;
    }

    public static function validateHost($host)
    {
        if (!isset($host['host'])) {
            throw new \InvalidArgumentException("'host' key is required.");
        }
        if (!isset($host['port'])) {
            throw new \InvalidArgumentException("'port' key is required.");
        }
        if (!isset($host['user'])) {
            throw new \InvalidArgumentException("'user' key is required.");
        }
        if (!isset($host['password'])) {
            throw new \InvalidArgumentException("'password' key is required.");
        }
    }
}

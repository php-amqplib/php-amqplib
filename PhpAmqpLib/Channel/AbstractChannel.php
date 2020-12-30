<?php

namespace PhpAmqpLib\Channel;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPInvalidFrameException;
use PhpAmqpLib\Exception\AMQPNoDataException;
use PhpAmqpLib\Exception\AMQPNotImplementedException;
use PhpAmqpLib\Exception\AMQPOutOfBoundsException;
use PhpAmqpLib\Exception\AMQPOutOfRangeException;
use PhpAmqpLib\Helper\DebugHelper;
use PhpAmqpLib\Helper\Protocol\MethodMap080;
use PhpAmqpLib\Helper\Protocol\MethodMap091;
use PhpAmqpLib\Helper\Protocol\Protocol080;
use PhpAmqpLib\Helper\Protocol\Protocol091;
use PhpAmqpLib\Helper\Protocol\Wait080;
use PhpAmqpLib\Helper\Protocol\Wait091;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire;
use PhpAmqpLib\Wire\AMQPReader;
use PhpAmqpLib\Wire\AMQPWriter;

abstract class AbstractChannel
{
    /**
     * @deprecated
     */
    const PROTOCOL_080 = Wire\Constants080::VERSION;

    /**
     * @deprecated
     */
    const PROTOCOL_091 = Wire\Constants091::VERSION;

    /**
     * Lower level queue for frames
     * @var array
     */
    protected $frameQueue = [];

    /**
     * Higher level queue for methods
     * @var array
     */
    protected $methodQueue = array();

    /** @var bool */
    protected $autoDecode = false;

    /** @var Wire\Constants */
    protected $constants;

    /** @var \PhpAmqpLib\Helper\DebugHelper */
    protected $debug;

    /** @var null|AbstractConnection */
    protected $connection;

    /** @var string */
    protected $protocolVersion;

    /** @var int */
    protected $maxBodySize;

    /** @var Protocol080|Protocol091 */
    protected $protocolWriter;

    /** @var Wait080|Wait091 */
    protected $waitHelper;

    /** @var MethodMap080|MethodMap091 */
    protected $methodMap;

    /** @var int */
    protected $channelId;

    /** @var AMQPReader */
    protected $msgPropertyReader;

    /** @var AMQPReader */
    protected $waitContentReader;

    /** @var AMQPReader */
    protected $dispatchReader;

    /**
     * @param AbstractConnection $connection
     * @param int                $channelId
     *
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function __construct(AbstractConnection $connection, $channelId)
    {
        $this->connection = $connection;
        $this->channelId = $channelId;
        $connection->channels[$channelId] = $this;

        $this->msgPropertyReader = new AMQPReader(null);
        $this->waitContentReader = new AMQPReader(null);
        $this->dispatchReader = new AMQPReader(null);

        $this->protocolVersion = self::getProtocolVersion();
        switch ($this->protocolVersion) {
            case Wire\Constants091::VERSION:
                $constantClass = Wire\Constants091::class;
                $this->protocolWriter = new Protocol091();
                $this->waitHelper = new Wait091();
                $this->methodMap = new MethodMap091();
                break;
            case Wire\Constants080::VERSION:
                $constantClass = Wire\Constants080::class;
                $this->protocolWriter = new Protocol080();
                $this->waitHelper = new Wait080();
                $this->methodMap = new MethodMap080();
                break;
            default:
                throw new AMQPNotImplementedException(sprintf(
                    'Protocol: %s not implemented.',
                    $this->protocolVersion
                ));
        }
        $this->constants = new $constantClass();
        $this->debug = new DebugHelper($this->constants);
    }

    /**
     * @return string
     * @throws AMQPOutOfRangeException
     */
    public static function getProtocolVersion()
    {
        $protocol = defined('AMQP_PROTOCOL') ? AMQP_PROTOCOL : Wire\Constants091::VERSION;
        //adding check here to catch unknown protocol ASAP, as this method may be called from the outside
        if (!in_array($protocol, array(Wire\Constants080::VERSION, Wire\Constants091::VERSION), true)) {
            throw new AMQPOutOfRangeException(sprintf('Protocol version %s not implemented.', $protocol));
        }

        return $protocol;
    }

    /**
     * @return string
     */
    public function getChannelId()
    {
        return $this->channelId;
    }

    /**
     * @param int $maxBytes Max message body size for this channel
     *
     * @return $this
     */
    public function setBodySizeLimit($maxBytes)
    {
        $maxBytes = (int)$maxBytes;

        if ($maxBytes > 0) {
            $this->maxBodySize = $maxBytes;
        }

        return $this;
    }

    /**
     * @return AbstractConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return array
     */
    public function getMethodQueue()
    {
        return $this->methodQueue;
    }

    /**
     * @return bool
     */
    public function hasPendingMethods()
    {
        return !empty($this->methodQueue);
    }

    /**
     * @param string           $methodSig
     * @param string           $args
     * @param AMQPMessage|null $amqpMessage
     *
     * @return mixed
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function dispatch($methodSig, $args, $amqpMessage)
    {
        if (!$this->methodMap->validMethod($methodSig)) {
            throw new AMQPNotImplementedException(
                sprintf(
                    'Unknown AMQP method "%s"',
                    $methodSig
                )
            );
        }

        $amqpMethod = $this->methodMap->getMethod($methodSig);

        if (!method_exists($this, $amqpMethod)) {
            throw new AMQPNotImplementedException(
                sprintf(
                    'Method: "%s" not implemented by class: %s',
                    $amqpMethod,
                    get_class($this)
                )
            );
        }

        $this->dispatchReader->reuse($args);

        if ($amqpMessage == null) {
            return call_user_func(array($this, $amqpMethod), $this->dispatchReader);
        }

        return call_user_func(array($this, $amqpMethod), $this->dispatchReader, $amqpMessage);
    }

    /**
     * @param int|float|null $timeout
     * @return array|mixed
     */
    public function nextFrame($timeout = 0)
    {
        $this->debug->debugMsg('waiting for a new frame');

        if (!empty($this->frameQueue)) {
            return array_shift($this->frameQueue);
        }

        return $this->connection->waitChannel($this->channelId, $timeout);
    }

    /**
     * @param array $methodSig
     * @param AMQPWriter|string $args
     */
    protected function sendMethodFrame($methodSig, $args = '')
    {
        if ($this->connection === null) {
            throw new AMQPChannelClosedException('Channel connection is closed.');
        }

        $this->connection->sendChannelMethodFrame($this->channelId, $methodSig, $args);
    }

    /**
     * This is here for performance reasons to batch calls to fwrite from basic.publish
     *
     * @param array                              $methodSig
     * @param AMQPWriter|string $args
     * @param AMQPWriter        $pkt
     *
     * @return AMQPWriter
     */
    protected function prepareMethodFrame($methodSig, $args = '', $pkt = null)
    {
        return $this->connection->prepareChannelMethodFrame($this->channelId, $methodSig, $args, $pkt);
    }

    /**
     * @return AMQPMessage
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function waitContent()
    {
        list($frameType, $payload) = $this->nextFrame();

        $this->validateHeaderFrame($frameType);

        $this->waitContentReader->reuse(mb_substr($payload, 0, 12, 'ASCII'));

        $classId = $this->waitContentReader->readShort();
        $weight = $this->waitContentReader->readShort();

        //hack to avoid creating new instances of AMQPReader;
        $this->msgPropertyReader->reuse(mb_substr($payload, 12, mb_strlen($payload, 'ASCII') - 12, 'ASCII'));

        return $this->createMessage(
            $this->msgPropertyReader,
            $this->waitContentReader
        );
    }

    /**
     * @param AMQPReader $propertyReader
     * @param AMQPReader $contentReader
     * @return AMQPMessage
     */
    protected function createMessage($propertyReader, $contentReader)
    {
        $body = '';
        $bodyReceivedBytes = 0;
        $message = new AMQPMessage();
        $message
            ->loadProperties($propertyReader)
            ->setBodySize($bodySize = $contentReader->readLonglong());

        while ($bodySize > $bodyReceivedBytes) {
            list($frameType, $payload) = $this->nextFrame();

            $this->validateBodyFrame($frameType);
            $bodyReceivedBytes += mb_strlen($payload, 'ASCII');

            if (is_int($this->maxBodySize) && $bodyReceivedBytes > $this->maxBodySize) {
                $message->setIsTruncated(true);
                continue;
            }

            $body .= $payload;
        }

        $message->setBody($body);

        return $message;
    }

    /**
     * Wait for some expected AMQP methods and dispatch to them.
     * Unexpected methods are queued up for later calls to this PHP
     * method.
     *
     * @param array $allowedMethods
     * @param bool $nonBlocking
     * @param int|float|null $timeout
     * @throws \PhpAmqpLib\Exception\AMQPOutOfBoundsException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @throws \ErrorException
     * @return mixed
     */
    public function wait($allowedMethods = null, $nonBlocking = false, $timeout = 0)
    {
        $this->debug->debugAllowedMethods($allowedMethods);

        $deferred = $this->processDeferredMethods($allowedMethods);
        if ($deferred['dispatch'] === true) {
            return $this->dispatchDeferredMethod($deferred['queued_method']);
        }

        // timeouts must be deactivated for non-blocking actions
        if (true === $nonBlocking) {
            $timeout = null;
        }

        // No deferred methods?  wait for new ones
        while (true) {
            try {
                list($frameType, $payload) = $this->nextFrame($timeout);
            } catch (AMQPNoDataException $e) {
                // no data ready for non-blocking actions - stop and exit
                break;
            } catch (AMQPConnectionClosedException $exception) {
                if ($this instanceof AMQPChannel) {
                    $this->doClose();
                }
                throw $exception;
            }

            $this->validateMethodFrame($frameType);
            $this->validateFramePayload($payload);

            $methodSig = $this->buildMethodSignature($payload);
            $args = $this->extractArgs($payload);

            $this->debug->debugMethodSignature('> %s', $methodSig);

            $amqpMessage = $this->maybeWaitForContent($methodSig);

            if ($this->shouldDispatchMethod($allowedMethods, $methodSig)) {
                return $this->dispatch($methodSig, $args, $amqpMessage);
            }

            // Wasn't what we were looking for? save it for later
            $this->debug->debugMethodSignature('Queueing for later: %s', $methodSig);
            $this->methodQueue[] = array($methodSig, $args, $amqpMessage);

            if ($nonBlocking) {
                break;
            }
        }
    }

    /**
     * @param array $allowedMethods
     * @return array
     */
    protected function processDeferredMethods($allowedMethods)
    {
        $dispatch = false;
        $queuedMethod = array();

        foreach ($this->methodQueue as $qk => $qm) {
            $this->debug->debugMsg('checking queue method ' . $qk);

            $methodSig = $qm[0];

            if ($allowedMethods == null || in_array($methodSig, $allowedMethods)) {
                unset($this->methodQueue[$qk]);
                $dispatch = true;
                $queuedMethod = $qm;
                break;
            }
        }

        return array('dispatch' => $dispatch, 'queued_method' => $queuedMethod);
    }

    /**
     * @param array $queuedMethod
     * @return mixed
     */
    protected function dispatchDeferredMethod($queuedMethod)
    {
        $this->debug->debugMethodSignature('Executing queued method: %s', $queuedMethod[0]);

        return $this->dispatch($queuedMethod[0], $queuedMethod[1], $queuedMethod[2]);
    }

    /**
     * @param int $frameType
     * @throws \PhpAmqpLib\Exception\AMQPInvalidFrameException
     */
    protected function validateMethodFrame($frameType)
    {
        $this->validateFrame($frameType, 1, 'AMQP method');
    }

    /**
     * @param int $frameType
     * @throws \PhpAmqpLib\Exception\AMQPInvalidFrameException
     */
    protected function validateHeaderFrame($frameType)
    {
        $this->validateFrame($frameType, 2, 'AMQP Content header');
    }

    /**
     * @param int $frameType
     * @throws \PhpAmqpLib\Exception\AMQPInvalidFrameException
     */
    protected function validateBodyFrame($frameType)
    {
        $this->validateFrame($frameType, 3, 'AMQP Content body');
    }

    /**
     * @param int $frameType
     * @param int $expectedType
     * @param string $expectedMessage
     */
    protected function validateFrame($frameType, $expectedType, $expectedMessage)
    {
        if ($frameType != $expectedType) {
            throw new AMQPInvalidFrameException(sprintf(
                'Expecting %s, received frame type %s (%s)',
                $expectedMessage,
                $frameType,
                $this->constants->getFrameType($frameType)
            ));
        }
    }

    /**
     * @param string $payload
     * @throws \PhpAmqpLib\Exception\AMQPOutOfBoundsException
     */
    protected function validateFramePayload($payload)
    {
        if (mb_strlen($payload, 'ASCII') < 4) {
            throw new AMQPOutOfBoundsException('Method frame too short');
        }
    }

    /**
     * @param string $payload
     * @return string
     */
    protected function buildMethodSignature($payload)
    {
        $methodSig_array = unpack('n2', mb_substr($payload, 0, 4, 'ASCII'));

        return sprintf('%s,%s', $methodSig_array[1], $methodSig_array[2]);
    }

    /**
     * @param string $payload
     * @return string
     */
    protected function extractArgs($payload)
    {
        return mb_substr($payload, 4, mb_strlen($payload, 'ASCII') - 4, 'ASCII');
    }

    /**
     * @param array|null $allowedMethods
     * @param string $methodSig
     * @return bool
     */
    protected function shouldDispatchMethod($allowedMethods, $methodSig)
    {
        return $allowedMethods == null
            || in_array($methodSig, $allowedMethods)
            || $this->constants->isCloseMethod($methodSig);
    }

    /**
     * @param string $methodSig
     * @return AMQPMessage|null
     */
    protected function maybeWaitForContent($methodSig)
    {
        $amqpMessage = null;
        if ($this->constants->isContentMethod($methodSig)) {
            $amqpMessage = $this->waitContent();
        }

        return $amqpMessage;
    }

    /**
     * @param callable $handler
     * @param array $arguments
     */
    protected function dispatchToHandler($handler, array $arguments = [])
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, $arguments);
        }
    }
}

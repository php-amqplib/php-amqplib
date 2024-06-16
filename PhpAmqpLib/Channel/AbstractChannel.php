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
     * @var \SplQueue|Frame[]
     */
    protected $frame_queue;

    /**
     * Higher level queue for methods
     * @var array
     */
    protected $method_queue = array();

    /** @var bool */
    protected $auto_decode = false;

    /** @var Wire\Constants */
    protected $constants;

    /** @var \PhpAmqpLib\Helper\DebugHelper */
    protected $debug;

    /** @var null|AbstractConnection */
    protected $connection;

    /**
     * @var string
     * @deprecated
     */
    protected $protocolVersion;

    /** @var int */
    protected $maxBodySize;

    /** @var Protocol080|Protocol091 */
    protected $protocolWriter;

    /** @var Wait080|Wait091 */
    protected $waitHelper;

    /** @var MethodMap080|MethodMap091 */
    protected $methodMap;

    /** @var int|null */
    protected $channel_id;

    /** @var Wire\AMQPBufferReader */
    protected $msg_property_reader;

    /** @var Wire\AMQPBufferReader */
    protected $dispatch_reader;

    /**
     * @param AbstractConnection $connection
     * @param int $channel_id
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function __construct(AbstractConnection $connection, $channel_id)
    {
        $this->connection = $connection;
        $this->channel_id = (int)$channel_id;
        $connection->channels[$channel_id] = $this;

        $this->msg_property_reader = new Wire\AMQPBufferReader('');
        $this->dispatch_reader = new Wire\AMQPBufferReader('');

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
        $this->frame_queue = new \SplQueue();
    }

    /**
     * @return string
     * @throws AMQPOutOfRangeException
     * @deprecated
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
     * @return int|null
     */
    public function getChannelId()
    {
        return $this->channel_id;
    }

    /**
     * @param int $max_bytes Max message body size for this channel
     * @return $this
     */
    public function setBodySizeLimit($max_bytes)
    {
        $max_bytes = (int) $max_bytes;

        if ($max_bytes > 0) {
            $this->maxBodySize = $max_bytes;
        }

        return $this;
    }

    /**
     * @return AbstractConnection|null
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
        return $this->method_queue;
    }

    /**
     * @return bool
     */
    public function hasPendingMethods()
    {
        return !empty($this->method_queue);
    }

    /**
     * @param string $method_sig
     * @param string $args
     * @param AMQPMessage|null $amqpMessage
     * @return mixed
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function dispatch($method_sig, $args, $amqpMessage)
    {
        if (!$this->methodMap->valid_method($method_sig)) {
            throw new AMQPNotImplementedException(sprintf(
                'Unknown AMQP method "%s"',
                $method_sig
            ));
        }

        $amqp_method = $this->methodMap->get_method($method_sig);

        if (!method_exists($this, $amqp_method)) {
            throw new AMQPNotImplementedException(sprintf(
                'Method: "%s" not implemented by class: %s',
                $amqp_method,
                get_class($this)
            ));
        }

        $this->dispatch_reader->reset($args);

        if ($amqpMessage === null) {
            return call_user_func(array($this, $amqp_method), $this->dispatch_reader);
        }

        return call_user_func(array($this, $amqp_method), $this->dispatch_reader, $amqpMessage);
    }

    /**
     * @param int|float|null $timeout
     * @return Frame
     */
    protected function next_frame($timeout = 0): Frame
    {
        $this->debug->debug_msg('waiting for a new frame');

        if (!$this->frame_queue->isEmpty()) {
            return $this->frame_queue->dequeue();
        }

        return $this->connection->wait_channel($this->channel_id, $timeout);
    }

    /**
     * @param array $method_sig
     * @param \PhpAmqpLib\Wire\AMQPWriter|string $args
     */
    protected function send_method_frame($method_sig, $args = '')
    {
        if ($this->connection === null) {
            throw new AMQPChannelClosedException('Channel connection is closed.');
        }

        $this->connection->send_channel_method_frame($this->channel_id, $method_sig, $args);
    }

    /**
     * This is here for performance reasons to batch calls to fwrite from basic.publish
     *
     * @param array $method_sig
     * @param \PhpAmqpLib\Wire\AMQPWriter|string $args
     * @param \PhpAmqpLib\Wire\AMQPWriter $pkt
     * @return \PhpAmqpLib\Wire\AMQPWriter
     */
    protected function prepare_method_frame($method_sig, $args = '', $pkt = null)
    {
        return $this->connection->prepare_channel_method_frame($this->channel_id, $method_sig, $args, $pkt);
    }

    /**
     * @return AMQPMessage
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     * @throws AMQPInvalidFrameException
     */
    public function wait_content(): AMQPMessage
    {
        $frame = $this->next_frame();
        $this->validate_frame($frame, Frame::TYPE_HEADER);
        $payload = $frame->getPayload();
        // skip class-id and weight(4 bytes) and start from size, everything else is properties
        // @link https://www.rabbitmq.com/resources/specs/amqp0-9-1.pdf 4.2.6.1 The Content Header
        $this->msg_property_reader->reset(mb_substr($payload, 4, null, 'ASCII'));
        $size = $this->msg_property_reader->read_longlong();

        return $this->createMessage(
            $this->msg_property_reader,
            $size
        );
    }

    protected function createMessage(AMQPReader $propertyReader, int $bodySize): AMQPMessage
    {
        $body = '';
        $bodyReceivedBytes = 0;
        $message = new AMQPMessage();
        $message
            ->load_properties($propertyReader)
            ->setBodySize($bodySize);

        while ($bodySize > $bodyReceivedBytes) {
            $frame = $this->next_frame();
            // @link https://www.rabbitmq.com/resources/specs/amqp0-9-1.pdf 4.2.6.2 The Content Body
            $this->validate_frame($frame, Frame::TYPE_BODY);
            $bodyReceivedBytes += $frame->getSize();

            if (is_int($this->maxBodySize) && $bodyReceivedBytes > $this->maxBodySize) {
                $message->setIsTruncated(true);
                continue;
            }

            $body .= $frame->getPayload();
        }

        $message->setBody($body);

        return $message;
    }

    /**
     * Wait for some expected AMQP methods and dispatch to them.
     * Unexpected methods are queued up for later calls to this PHP
     * method.
     *
     * @param array|null $allowed_methods
     * @param bool $non_blocking
     * @param int|float|null $timeout
     * @return mixed
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @throws \PhpAmqpLib\Exception\AMQPConnectionClosedException
     * @throws AMQPOutOfBoundsException
     */
    public function wait($allowed_methods = null, $non_blocking = false, $timeout = 0)
    {
        $this->debug->debug_allowed_methods($allowed_methods);

        $deferred = $this->process_deferred_methods($allowed_methods);
        if ($deferred['dispatch'] === true) {
            return $this->dispatch_deferred_method($deferred['queued_method']);
        }

        // timeouts must be deactivated for non-blocking actions
        if (true === $non_blocking) {
            $timeout = null;
        }

        // No deferred methods?  wait for new ones
        while (true) {
            try {
                $frame = $this->next_frame($timeout);
            } catch (AMQPNoDataException $e) {
                // no data ready for non-blocking actions - stop and exit
                break;
            } catch (AMQPConnectionClosedException $exception) {
                if ($this instanceof AMQPChannel) {
                    $this->do_close();
                }
                throw $exception;
            }

            $this->validate_method_frame($frame);
            $this->validate_frame_payload($frame);
            $payload = $frame->getPayload();
            $method = $this->parseMethod($payload);
            $method_sig = $method->getSignature();

            $this->debug->debug_method_signature('> %s', $method_sig);

            $amqpMessage = $this->maybe_wait_for_content($method_sig);

            if ($this->should_dispatch_method($allowed_methods, $method_sig)) {
                return $this->dispatch($method_sig, $method->getArguments(), $amqpMessage);
            }

            // Wasn't what we were looking for? save it for later
            $this->debug->debug_method_signature('Queueing for later: %s', $method_sig);
            $this->method_queue[] = array($method_sig, $method->getArguments(), $amqpMessage);

            if ($non_blocking) {
                break;
            }
        }
    }

    /**
     * @param array|null $allowed_methods
     * @return array
     */
    protected function process_deferred_methods($allowed_methods)
    {
        $dispatch = false;
        $queued_method = array();

        foreach ($this->method_queue as $qk => $qm) {
            $this->debug->debug_msg('checking queue method ' . $qk);

            $method_sig = $qm[0];

            if ($allowed_methods === null || in_array($method_sig, $allowed_methods, true)) {
                unset($this->method_queue[$qk]);
                $dispatch = true;
                $queued_method = $qm;
                break;
            }
        }

        return array('dispatch' => $dispatch, 'queued_method' => $queued_method);
    }

    /**
     * @param array $queued_method
     * @return mixed
     */
    protected function dispatch_deferred_method($queued_method)
    {
        $this->debug->debug_method_signature('Executing queued method: %s', $queued_method[0]);

        return $this->dispatch($queued_method[0], $queued_method[1], $queued_method[2]);
    }

    /**
     * @param Frame $frame
     * @throws \PhpAmqpLib\Exception\AMQPInvalidFrameException
     */
    protected function validate_method_frame(Frame $frame): void
    {
        $this->validate_frame($frame, Frame::TYPE_METHOD);
    }

    /**
     * @param Frame $frame
     * @param int $expectedType
     * @throws AMQPInvalidFrameException
     */
    protected function validate_frame(Frame $frame, int $expectedType): void
    {
        if ($frame->getType() !== $expectedType) {
            throw new AMQPInvalidFrameException(sprintf(
                'Expecting %u, received frame type %s (%s)',
                $expectedType,
                $frame->getType(),
                $this->constants->getFrameType($frame->getType())
            ));
        }
    }

    /**
     * @param Frame $frame
     * @throws AMQPOutOfBoundsException
     * @throws AMQPInvalidFrameException
     */
    protected function validate_frame_payload(Frame $frame): void
    {
        $payload = $frame->getPayload();
        $payloadSize = mb_strlen($payload, 'ASCII');
        if ($payloadSize < 4) {
            throw new AMQPOutOfBoundsException('Method frame too short');
        }
        if ($payloadSize !== $frame->getSize()) {
            throw new AMQPInvalidFrameException('Frame size does not match payload');
        }
    }

    protected function parseMethod(string $payload): Method
    {
        $result = unpack('n2method/a*args', $payload);

        return new Method($result['method1'], $result['method2'], $result['args']);
    }

    /**
     * @param array|null $allowed_methods
     * @param string $method_sig
     * @return bool
     */
    protected function should_dispatch_method($allowed_methods, $method_sig)
    {
        return $allowed_methods === null
            || in_array($method_sig, $allowed_methods, true)
            || $this->constants->isCloseMethod($method_sig);
    }

    /**
     * @param string $method_sig
     * @return AMQPMessage|null
     */
    protected function maybe_wait_for_content($method_sig)
    {
        $amqpMessage = null;
        if ($this->constants->isContentMethod($method_sig)) {
            $amqpMessage = $this->wait_content();
        }

        return $amqpMessage;
    }

    /**
     * @param callable $handler
     * @param array $arguments
     */
    protected function dispatch_to_handler($handler, array $arguments = [])
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, $arguments);
        }
    }
}

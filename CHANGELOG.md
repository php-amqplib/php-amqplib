# Changelog

All Notable changes to `php-amqplib` will be documented in this file

## 2.7.2 - 2018-02-11

[GitHub Milestone](https://github.com/php-amqplib/php-amqplib/milestone/5)

- PHP `5.3` compatibility [PR](https://github.com/php-amqplib/php-amqplib/issues/539)

## 2.7.1 - 2018-02-01

- Support PHPUnit 6 [PR](https://github.com/php-amqplib/php-amqplib/pull/530)
- Use `tcp_nodelay` for `StreamIO` [PR](https://github.com/php-amqplib/php-amqplib/pull/517)
- Pass connection timeout to `wait` method [PR](https://github.com/php-amqplib/php-amqplib/pull/512)
- Fix possible indefinite waiting for data in StreamIO [PR](https://github.com/php-amqplib/php-amqplib/pull/423), [PR](https://github.com/php-amqplib/php-amqplib/pull/534)
- Change protected method check_heartbeat to public [PR](https://github.com/php-amqplib/php-amqplib/pull/520)
- Ensure access levels are consistent for calling `check_heartbeat` [PR](https://github.com/php-amqplib/php-amqplib/pull/535)

## 2.7.0 - 2017-09-20

### Added
- Increased overall test coverage
- Bring heartbeat support to socket connection
- Add message delivery tag for publisher confirms
- Add support for serializing DateTimeImmutable objects

### Fixed
- Fixed infinite loop on reconnect - check_heartbeat
- Fixed signal handling exit example
- Fixed exchange_unbind arguments
- Fixed invalid annotation for channel_id
- Fixed socket null error on php 5.3 version
- Fixed timeout parameters on HHVM before calling stream_select

### Changed
- declare(ticks=1) no longer needed after PHP5.3 / amqplib 2.4.1
- Minor DebugHelper improvements

### Enhancements
- Add extensions requirements to README.md
- Add PHP 7.1 to Travis build
- Reduce memory usage in StreamIO::write()
- Re-enable heartbeats after reconnection

## 2.6.3 - 2016-04-11

### Added
- Added the ability to set timeout as float

### Fixed
- Fixed restoring of error_handler on connection error

### Enhancements
- Verify read_write_timeout is at least 2x the heartbeat (if set)
- Many PHPDoc fixes
- Throw exception when trying to create an exchange on a closed connection

## 2.6.2 - 2016-03-02

### Added
- Added AMQPLazySocketConnection
- AbstractConnection::getServerProperties method to retrieve server properties.
- AMQPReader::wait() will throw IOWaitException on stream_select failure
- Add PHPDocs to Auto-generated Protocol Classes

### Fixed
- Disable heartbeat when closing connection
- Fix for when the default error handler is not restored in StreamIO

### Enhancements
- Cleanup tests and improve testing performance
- Confirm received valid frame type on wait_frame in AbstractConnection
- Update DEMO files closer to PSR-2 standards

## 2.6.1 - 2016-02-12

### Added
- Add constants for delivery modes to AMQPMessage

### Fixed
- Fix some PHPDoc problems
- AbstractCollection value de/encoding on PHP7
- StreamIO: fix "bad write retry" in SSL mode

### Enhancements
- Update PHPUnit configuration
- Add scrutinizer-ci configuration
- Organizational changes from videlalvaro to php-amqplib org
- Minor complexity optimizations, code organization, and code cleanup

## 2.6.0 - 2015-09-23

### BC Breaking Changes
- The `AMQPStreamConnection` class now throws `ErrorExceptions` when errors happen while reading/writing to the network.

### Added
- Heartbeat frames will decrease the timeout used when calling wait_channel - heartbeat frames do not reset the timeout

### Fixed
- Declared the class AbstractChannel as being an abstract class
- Reads, writes and signals respond immediately instead of waiting for a timeout
- Fatal error in some cases on Channel.wait with timeout when RabbitMQ restarted
- Remove warning when trying to push a deferred frame

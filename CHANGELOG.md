# Changelog

All Notable changes to `php-amqplib` will be documented in this file

## [Unreleased]

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

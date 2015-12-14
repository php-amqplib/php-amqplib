# Changelog

All Notable changes to `PhpAmqpLib` will be documented in this file

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

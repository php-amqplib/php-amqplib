# Changelog

## [v3.2.0](https://github.com/php-amqplib/php-amqplib/tree/v3.2.0) (2022-03-10)

[GitHub Milestone](https://github.com/php-amqplib/php-amqplib/milestone/24?closed=1)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v3.1.2...v3.2.0)

## [v3.1.2](https://github.com/php-amqplib/php-amqplib/tree/v3.1.2) (2022-01-18)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v3.1.1...v3.1.2)

**Implemented enhancements:**

- use github changelog generator [\#970](https://github.com/php-amqplib/php-amqplib/pull/970) ([ramunasd](https://github.com/ramunasd))

**Fixed bugs:**

- Always restore original error handler after socket/stream actions [\#969](https://github.com/php-amqplib/php-amqplib/pull/969) ([ramunasd](https://github.com/ramunasd))

**Closed issues:**

- Deprecation warnings on ArrayAccess methods [\#967](https://github.com/php-amqplib/php-amqplib/issues/967)

**Merged pull requests:**

- add return type hints in AMQPAbstractCollection [\#968](https://github.com/php-amqplib/php-amqplib/pull/968) ([ramunasd](https://github.com/ramunasd))

## [v3.1.1](https://github.com/php-amqplib/php-amqplib/tree/v3.1.1) (2021-12-03)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v3.1.0...v3.1.1)

**Fixed bugs:**

- fix deprecation notice from stream\_select\(\) on PHP8.1 [\#963](https://github.com/php-amqplib/php-amqplib/pull/963) ([ramunasd](https://github.com/ramunasd))

**Closed issues:**

- Support for PHP 8.1 [\#959](https://github.com/php-amqplib/php-amqplib/issues/959)
- php.56 overtime [\#952](https://github.com/php-amqplib/php-amqplib/issues/952)

## [v3.1.0](https://github.com/php-amqplib/php-amqplib/tree/v3.1.0) (2021-10-22)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v3.0.0...v3.1.0)

**Implemented enhancements:**

- drop support for PHP7.0 [\#949](https://github.com/php-amqplib/php-amqplib/pull/949) ([ramunasd](https://github.com/ramunasd))
- Add support for floating point values in tables/array [\#945](https://github.com/php-amqplib/php-amqplib/pull/945) ([ramunasd](https://github.com/ramunasd))

**Fixed bugs:**

- Consumer fails with AMQP-rabbit doesn't define data of type \[d\] [\#924](https://github.com/php-amqplib/php-amqplib/issues/924)
- Fixed composer php version constraint [\#916](https://github.com/php-amqplib/php-amqplib/pull/916) ([rkrx](https://github.com/rkrx))

**Closed issues:**

- How $channel-\>wait\(\) work on loop forever [\#939](https://github.com/php-amqplib/php-amqplib/issues/939)
- Severity: error --\> Exception: stream\_socket\_client\(\): unable to connect to [\#938](https://github.com/php-amqplib/php-amqplib/issues/938)
- stream\_socket\_client\(\): unable to connect to ssl: connection time out [\#937](https://github.com/php-amqplib/php-amqplib/issues/937)
- The header isn't fragmented causing large headers to hit the maximum frame size. [\#934](https://github.com/php-amqplib/php-amqplib/issues/934)
- Keeping a connection open for publishing [\#932](https://github.com/php-amqplib/php-amqplib/issues/932)
- How to locate problems by exception code -\> CHANNEL\_ERROR - expected 'channel.open'\(40, 10\) [\#930](https://github.com/php-amqplib/php-amqplib/issues/930)
- php5.6.9  Unable to use AMQPStreamConnection to connect RabbitServer but AMQPSocketConnection is normal  [\#928](https://github.com/php-amqplib/php-amqplib/issues/928)
- How to start a quorum queue? [\#921](https://github.com/php-amqplib/php-amqplib/issues/921)
- prefetch\_count seems to consume always only 1 message [\#919](https://github.com/php-amqplib/php-amqplib/issues/919)
- Can't connect to ssl amqp hosts. [\#918](https://github.com/php-amqplib/php-amqplib/issues/918)
- Updating "phpseclib/phpseclib" is necessary! [\#914](https://github.com/php-amqplib/php-amqplib/issues/914)
- README - Non-existant code of conduct file [\#913](https://github.com/php-amqplib/php-amqplib/issues/913)
- How to get list of consumers with tags for a specific queue [\#910](https://github.com/php-amqplib/php-amqplib/issues/910)
- consumer\_tag: Consumer identifier [\#909](https://github.com/php-amqplib/php-amqplib/issues/909)
- AMQPLazyConnection::create\_connection does not work [\#798](https://github.com/php-amqplib/php-amqplib/issues/798)

**Merged pull requests:**

- throw exception on attempt to create lazy connection to multiple hosts [\#951](https://github.com/php-amqplib/php-amqplib/pull/951) ([ramunasd](https://github.com/ramunasd))
- Fix static analysis warnings [\#948](https://github.com/php-amqplib/php-amqplib/pull/948) ([ramunasd](https://github.com/ramunasd))
- Add PHP 8.1 support [\#929](https://github.com/php-amqplib/php-amqplib/pull/929) ([javer](https://github.com/javer))
- Use correct default for read\_write\_timeout in AMQPStreamConnection\#try\_create\_connection [\#923](https://github.com/php-amqplib/php-amqplib/pull/923) ([bezhermoso](https://github.com/bezhermoso))
- Improved examples and dosc [\#917](https://github.com/php-amqplib/php-amqplib/pull/917) ([corpsee](https://github.com/corpsee))
- Fix code style: unnecessary space [\#915](https://github.com/php-amqplib/php-amqplib/pull/915) ([maximal](https://github.com/maximal))

## [v3.0.0](https://github.com/php-amqplib/php-amqplib/tree/v3.0.0) (2021-03-16)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v3.0.0-rc2...v3.0.0)

**Merged pull requests:**

- Change php required version in composer.json [\#905](https://github.com/php-amqplib/php-amqplib/pull/905) ([adoy](https://github.com/adoy))

## [v3.0.0-rc2](https://github.com/php-amqplib/php-amqplib/tree/v3.0.0-rc2) (2021-03-09)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v3.0.0-rc1...v3.0.0-rc2)

**Fixed bugs:**

- PHP 8 support [\#904](https://github.com/php-amqplib/php-amqplib/pull/904) ([patrickkusebauch](https://github.com/patrickkusebauch))

**Closed issues:**

- PHP 8 support issue [\#903](https://github.com/php-amqplib/php-amqplib/issues/903)

## [v3.0.0-rc1](https://github.com/php-amqplib/php-amqplib/tree/v3.0.0-rc1) (2021-03-08)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.12.3...v3.0.0-rc1)

**Implemented enhancements:**

- Support php 8.0 [\#858](https://github.com/php-amqplib/php-amqplib/pull/858) ([axxapy](https://github.com/axxapy))

**Fixed bugs:**

- BigInteger breaks authoritative class maps [\#885](https://github.com/php-amqplib/php-amqplib/issues/885)
- fix ValueError on closed or broken socket [\#888](https://github.com/php-amqplib/php-amqplib/pull/888) ([ramunasd](https://github.com/ramunasd))

**Merged pull requests:**

- Drop deprecated things [\#897](https://github.com/php-amqplib/php-amqplib/pull/897) ([ramunasd](https://github.com/ramunasd))
- Allow to use SSL connection as lazy [\#893](https://github.com/php-amqplib/php-amqplib/pull/893) ([adombrovsky](https://github.com/adombrovsky))
- Drop support for PHP5.6 [\#884](https://github.com/php-amqplib/php-amqplib/pull/884) ([ramunasd](https://github.com/ramunasd))
- feat\(Composer\) run test composer 2. [\#882](https://github.com/php-amqplib/php-amqplib/pull/882) ([Yozhef](https://github.com/Yozhef))
- feat\(Travis\) remove travis. [\#881](https://github.com/php-amqplib/php-amqplib/pull/881) ([Yozhef](https://github.com/Yozhef))
- feat\(CodeCov\) add Codecov phpunit code coverage. [\#880](https://github.com/php-amqplib/php-amqplib/pull/880) ([Yozhef](https://github.com/Yozhef))
- Phpdoc types and minor improvements [\#869](https://github.com/php-amqplib/php-amqplib/pull/869) ([andrew-demb](https://github.com/andrew-demb))

## [v2.12.3](https://github.com/php-amqplib/php-amqplib/tree/v2.12.3) (2021-03-01)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/2.12.2...v2.12.3)

**Fixed bugs:**

- ValueError exception in PHP8 [\#883](https://github.com/php-amqplib/php-amqplib/issues/883)

**Closed issues:**

- process multiple messages at the same  [\#898](https://github.com/php-amqplib/php-amqplib/issues/898)
- application\_headers vs headers [\#890](https://github.com/php-amqplib/php-amqplib/issues/890)
- Remove support for PHP 5.X [\#877](https://github.com/php-amqplib/php-amqplib/issues/877)
- Ideas and deprecations for next major release 4.0 [\#662](https://github.com/php-amqplib/php-amqplib/issues/662)

## [2.12.2](https://github.com/php-amqplib/php-amqplib/tree/2.12.2) (2021-02-12)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.12.1...2.12.2)

**Implemented enhancements:**

- TLS connection example needed in readme [\#801](https://github.com/php-amqplib/php-amqplib/issues/801)
- Add support for next major version of phpseclib/phpseclib [\#875](https://github.com/php-amqplib/php-amqplib/pull/875) ([ramunasd](https://github.com/ramunasd))

**Fixed bugs:**

- Provide AMQPTable to exchange\_declare [\#873](https://github.com/php-amqplib/php-amqplib/issues/873)
- fix annotation when AMQPTable is allowed variable type [\#874](https://github.com/php-amqplib/php-amqplib/pull/874) ([ramunasd](https://github.com/ramunasd))
- fix PCNTL heartbeat signal registration [\#866](https://github.com/php-amqplib/php-amqplib/pull/866) ([laurynasgadl](https://github.com/laurynasgadl))

**Closed issues:**

- Type definition delivery tag differs [\#876](https://github.com/php-amqplib/php-amqplib/issues/876)
- PHP 8 Deprecate required parameters after optional parameters issue [\#870](https://github.com/php-amqplib/php-amqplib/issues/870)
- PCNTLHeartbeatSender would be never triggered again when connection in writing status  [\#865](https://github.com/php-amqplib/php-amqplib/issues/865)
- PHP8 deprecation warnings [\#860](https://github.com/php-amqplib/php-amqplib/issues/860)
- PHP 8.0.0 Deprecated: Required parameter ... follows optional parameter ... in  [\#856](https://github.com/php-amqplib/php-amqplib/issues/856)
- The connection would lost on some environment and cause destruct failed [\#849](https://github.com/php-amqplib/php-amqplib/issues/849)
- About message body string "quit" [\#848](https://github.com/php-amqplib/php-amqplib/issues/848)
- Why is the client disconnecting automatically with no errors nor Exceptions? [\#847](https://github.com/php-amqplib/php-amqplib/issues/847)
- PHP 8: Required parameter $io follows optional parameter $vhost [\#846](https://github.com/php-amqplib/php-amqplib/issues/846)
- AMQPProtocolException phpdoc arguments type annotations are swapped [\#844](https://github.com/php-amqplib/php-amqplib/issues/844)
- Multiple consumers at one connection [\#843](https://github.com/php-amqplib/php-amqplib/issues/843)
- Too many publishers produce many messages In an hour, occasionally cause an exception: "stream\_socket\_client\(\): unable to connect to tcp://RABBITMQ-\*\*\*amazonaws.com:5672 \(Connection timed out\)" [\#842](https://github.com/php-amqplib/php-amqplib/issues/842)
- Framing Error trying to connect to a RabbitMQ docker Container [\#840](https://github.com/php-amqplib/php-amqplib/issues/840)
- PHP Fatal error:  Uncaught exception 'PhpAmqpLib\Exception\AMQPTimeoutException' with message 'The connection timed out after 3 sec while awaiting incoming data' [\#839](https://github.com/php-amqplib/php-amqplib/issues/839)
- The dependency phpseclib needs an update to version 3.\* [\#867](https://github.com/php-amqplib/php-amqplib/issues/867)

**Merged pull requests:**

- Fixed AMQPProtocolException phpdoc arguments type annotations [\#845](https://github.com/php-amqplib/php-amqplib/pull/845) ([zerkms](https://github.com/zerkms))
- Change phpdoc $delivery\_tag type to int [\#838](https://github.com/php-amqplib/php-amqplib/pull/838) ([autowp](https://github.com/autowp))
- Update documentation on published release [\#837](https://github.com/php-amqplib/php-amqplib/pull/837) ([ramunasd](https://github.com/ramunasd))
- perform CI tests using github actions [\#836](https://github.com/php-amqplib/php-amqplib/pull/836) ([ramunasd](https://github.com/ramunasd))
- PSR 12 [\#868](https://github.com/php-amqplib/php-amqplib/pull/868) ([andrew-demb](https://github.com/andrew-demb))
- feat \(Code Style\) start integration PSR-2. [\#859](https://github.com/php-amqplib/php-amqplib/pull/859) ([Yozhef](https://github.com/Yozhef))
- Implement \ArrayAccess in AMQPAbstractCollection [\#850](https://github.com/php-amqplib/php-amqplib/pull/850) ([idsulik](https://github.com/idsulik))

## [v2.12.1](https://github.com/php-amqplib/php-amqplib/tree/v2.12.1) (2020-09-25)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.12.0...v2.12.1)

**Implemented enhancements:**

- Use docker containers for broker and proxy in travis CI tests [\#831](https://github.com/php-amqplib/php-amqplib/pull/831) ([ramunasd](https://github.com/ramunasd))

**Fixed bugs:**

- wait\_for\_pending\_acks results in: LogicException\("Delivery tag cannot be changed"\) [\#827](https://github.com/php-amqplib/php-amqplib/issues/827)
- Error Connecting to server\(0\): [\#825](https://github.com/php-amqplib/php-amqplib/issues/825)
- validate basic\_consume\(\) arguments and avoid invalid callbacks [\#834](https://github.com/php-amqplib/php-amqplib/pull/834) ([ramunasd](https://github.com/ramunasd))

**Closed issues:**

- Does the library supports federation conf? [\#826](https://github.com/php-amqplib/php-amqplib/issues/826)
- Publishing not happend after publishing to non-existent exchange [\#823](https://github.com/php-amqplib/php-amqplib/issues/823)
- Tests should run with TLS enabled [\#758](https://github.com/php-amqplib/php-amqplib/issues/758)

**Merged pull requests:**

- SSL tests and fixed demo [\#832](https://github.com/php-amqplib/php-amqplib/pull/832) ([ramunasd](https://github.com/ramunasd))
- fix LogicException while waiting for pending broker ack [\#830](https://github.com/php-amqplib/php-amqplib/pull/830) ([ramunasd](https://github.com/ramunasd))
- revert \#785 'Enable TLS SNI by default' [\#829](https://github.com/php-amqplib/php-amqplib/pull/829) ([ramunasd](https://github.com/ramunasd))

## [v2.12.0](https://github.com/php-amqplib/php-amqplib/tree/v2.12.0) (2020-08-25)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.11.3...v2.12.0)

**Implemented enhancements:**

- CI tests for PHP 7.4 [\#800](https://github.com/php-amqplib/php-amqplib/pull/800) ([ramunasd](https://github.com/ramunasd))
- AMQPMessage new interface [\#799](https://github.com/php-amqplib/php-amqplib/pull/799) ([ramunasd](https://github.com/ramunasd))
- Enable TLS SNI by setting peer\_name to $host in $ssl\_options [\#785](https://github.com/php-amqplib/php-amqplib/pull/785) ([carlhoerberg](https://github.com/carlhoerberg))

**Fixed bugs:**

- Adding exception handling for better user experience [\#810](https://github.com/php-amqplib/php-amqplib/issues/810)
- Possible blocking connection even when connection\_timeout is specified [\#804](https://github.com/php-amqplib/php-amqplib/issues/804)
- use simple output instead of STDOUT in debug helper [\#819](https://github.com/php-amqplib/php-amqplib/pull/819) ([ramunasd](https://github.com/ramunasd))
- add missing timeout param for connection handshake response [\#812](https://github.com/php-amqplib/php-amqplib/pull/812) ([ramunasd](https://github.com/ramunasd))

**Closed issues:**

- Setting x-ha-policy from client side is not longer available since version 3.0 [\#811](https://github.com/php-amqplib/php-amqplib/issues/811)
- Debug in some cases is not working - possible fix - line 29 in DebugHelper [\#809](https://github.com/php-amqplib/php-amqplib/issues/809)
- when I want to use publish\_batch in confirm: 2 [\#807](https://github.com/php-amqplib/php-amqplib/issues/807)
- when I want to use publish\_batch in confirm [\#806](https://github.com/php-amqplib/php-amqplib/issues/806)
- NullClasses for testing [\#802](https://github.com/php-amqplib/php-amqplib/issues/802)

**Merged pull requests:**

- AbstractIO::select\(\) never returns false [\#817](https://github.com/php-amqplib/php-amqplib/pull/817) ([szepeviktor](https://github.com/szepeviktor))
- Tidy up CI configuration [\#816](https://github.com/php-amqplib/php-amqplib/pull/816) ([szepeviktor](https://github.com/szepeviktor))
- Add signal-based heartbeat option [\#815](https://github.com/php-amqplib/php-amqplib/pull/815) ([laurynasgadl](https://github.com/laurynasgadl))
- add type check for basic\_consume\(\) callback [\#814](https://github.com/php-amqplib/php-amqplib/pull/814) ([ramunasd](https://github.com/ramunasd))
- Exclude non-essential files from dist [\#796](https://github.com/php-amqplib/php-amqplib/pull/796) ([fedotov-as](https://github.com/fedotov-as))

## [v2.11.3](https://github.com/php-amqplib/php-amqplib/tree/v2.11.3) (2020-05-13)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.11.2...v2.11.3)

**Fixed bugs:**

- Unexpected heartbeat missed exception [\#793](https://github.com/php-amqplib/php-amqplib/issues/793)
- Fix unexpected missed heartbeat exception [\#794](https://github.com/php-amqplib/php-amqplib/pull/794) ([ramunasd](https://github.com/ramunasd))

## [v2.11.2](https://github.com/php-amqplib/php-amqplib/tree/v2.11.2) (2020-04-30)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.11.1...v2.11.2)

**Fixed bugs:**

- Perform socket/stream select before data write [\#791](https://github.com/php-amqplib/php-amqplib/pull/791) ([ramunasd](https://github.com/ramunasd))

**Closed issues:**

- Fatal error: Uncaught exception 'PhpAmqpLib\Exception\AMQPConnectionClosedException' with message 'FRAME\_ERROR - type 2, first 16 octets [\#789](https://github.com/php-amqplib/php-amqplib/issues/789)
- Incorrect behaviour when heartbeat is missing [\#787](https://github.com/php-amqplib/php-amqplib/issues/787)
- How to know When rabbitmq server get last heartbeat from client? [\#783](https://github.com/php-amqplib/php-amqplib/issues/783)

## [v2.11.1](https://github.com/php-amqplib/php-amqplib/tree/v2.11.1) (2020-02-24)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.11.0...v2.11.1)

**Fixed bugs:**

- Incorrect documentation for AMQPMessage constructor \(and others\) [\#769](https://github.com/php-amqplib/php-amqplib/issues/769)
- Handling of SOCKET\_EAGAIN in StreamIO not working in PHP 7.4 [\#764](https://github.com/php-amqplib/php-amqplib/issues/764)
- fix: ensure hosts is an array, otherwise latest\_exception can be null [\#778](https://github.com/php-amqplib/php-amqplib/pull/778) ([mr-feek](https://github.com/mr-feek))
- change phpDocumentator template, fix incorrect constructor documentation [\#771](https://github.com/php-amqplib/php-amqplib/pull/771) ([ramunasd](https://github.com/ramunasd))

**Closed issues:**

- circular reference [\#759](https://github.com/php-amqplib/php-amqplib/issues/759)

**Merged pull requests:**

- Add package meta class [\#782](https://github.com/php-amqplib/php-amqplib/pull/782) ([ramunasd](https://github.com/ramunasd))
- Blocked connection check [\#779](https://github.com/php-amqplib/php-amqplib/pull/779) ([ramunasd](https://github.com/ramunasd))
- Code style and static analysis warnings [\#768](https://github.com/php-amqplib/php-amqplib/pull/768) ([ramunasd](https://github.com/ramunasd))
- Mention AMQProxy as related library [\#767](https://github.com/php-amqplib/php-amqplib/pull/767) ([johanrhodin](https://github.com/johanrhodin))
- Fix comments [\#766](https://github.com/php-amqplib/php-amqplib/pull/766) ([Yurunsoft](https://github.com/Yurunsoft))
- Restrict PHP 7.4.0 - 7.4.1 due to a PHP bug [\#765](https://github.com/php-amqplib/php-amqplib/pull/765) ([Majkl578](https://github.com/Majkl578))

## [v2.11.0](https://github.com/php-amqplib/php-amqplib/tree/v2.11.0) (2019-11-19)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.10.1...v2.11.0)

**Implemented enhancements:**

- Remove bcmath dependency [\#754](https://github.com/php-amqplib/php-amqplib/pull/754) ([ramunasd](https://github.com/ramunasd))
- Run phpunit on appveyor [\#751](https://github.com/php-amqplib/php-amqplib/pull/751) ([ramunasd](https://github.com/ramunasd))

**Fixed bugs:**

- Exception while handling AMQPTimeoutException [\#752](https://github.com/php-amqplib/php-amqplib/issues/752)
- Fix AMQPTimeoutException handling [\#753](https://github.com/php-amqplib/php-amqplib/pull/753) ([kozlice](https://github.com/kozlice))

**Closed issues:**

- Amazon MQ amqp+ssl [\#757](https://github.com/php-amqplib/php-amqplib/issues/757)
- shell\_exec\(\): Unable to execute '' [\#756](https://github.com/php-amqplib/php-amqplib/issues/756)
- Remove bcmath dependency [\#694](https://github.com/php-amqplib/php-amqplib/issues/694)

**Merged pull requests:**

- Fix phpunit tests reported as risked [\#755](https://github.com/php-amqplib/php-amqplib/pull/755) ([ramunasd](https://github.com/ramunasd))
- throw AMQPConnectionClosedException when broker wants to close connection [\#750](https://github.com/php-amqplib/php-amqplib/pull/750) ([ramunasd](https://github.com/ramunasd))
- Add support for PLAIN authentication method [\#749](https://github.com/php-amqplib/php-amqplib/pull/749) ([ramunasd](https://github.com/ramunasd))

## [v2.10.1](https://github.com/php-amqplib/php-amqplib/tree/v2.10.1) (2019-10-10)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.10.0...v2.10.1)

**Implemented enhancements:**

- Refactor channel constant classes [\#732](https://github.com/php-amqplib/php-amqplib/pull/732) ([ramunasd](https://github.com/ramunasd))

**Fixed bugs:**

- Channel gets stuck if user `wait_for_pending_acks` [\#720](https://github.com/php-amqplib/php-amqplib/issues/720)
- Update amqp\_connect\_multiple\_hosts.php [\#740](https://github.com/php-amqplib/php-amqplib/pull/740) ([nguyendachuy](https://github.com/nguyendachuy))
- Fix fatal error in skipped tests [\#736](https://github.com/php-amqplib/php-amqplib/pull/736) ([ramunasd](https://github.com/ramunasd))
- Fix wrong headers exchange demo [\#735](https://github.com/php-amqplib/php-amqplib/pull/735) ([ramunasd](https://github.com/ramunasd))
- Fix infinite wait for pending acks [\#733](https://github.com/php-amqplib/php-amqplib/pull/733) ([ramunasd](https://github.com/ramunasd))

**Closed issues:**

- basic\_publish and memory alarms [\#743](https://github.com/php-amqplib/php-amqplib/issues/743)
- Fail to apply arguments to  queue\_declare [\#741](https://github.com/php-amqplib/php-amqplib/issues/741)
- Connection timeout error [\#739](https://github.com/php-amqplib/php-amqplib/issues/739)
- Exchanges list [\#734](https://github.com/php-amqplib/php-amqplib/issues/734)
- Cannot create a durable queue [\#731](https://github.com/php-amqplib/php-amqplib/issues/731)
- isConnected remains true while AMQPConnectionClosedException is thrown [\#730](https://github.com/php-amqplib/php-amqplib/issues/730)
- Use v2.9~2.10, the CPU will 99% when waiting for new messages. v2.8 has no such problem. [\#729](https://github.com/php-amqplib/php-amqplib/issues/729)
- How to set connection name ? [\#728](https://github.com/php-amqplib/php-amqplib/issues/728)
- Headers exchange - php example [\#554](https://github.com/php-amqplib/php-amqplib/issues/554)
- AMQPMessage::basic\_consume + $nowait=null results in $nowait=true [\#422](https://github.com/php-amqplib/php-amqplib/issues/422)

**Merged pull requests:**

- Specify language id in the code blocks [\#747](https://github.com/php-amqplib/php-amqplib/pull/747) ([funivan](https://github.com/funivan))
- Update version number to 2.10 [\#746](https://github.com/php-amqplib/php-amqplib/pull/746) ([ramunasd](https://github.com/ramunasd))
- Remove phpDocumentator from dev dependencies [\#745](https://github.com/php-amqplib/php-amqplib/pull/745) ([ramunasd](https://github.com/ramunasd))
- Typo [\#738](https://github.com/php-amqplib/php-amqplib/pull/738) ([marianofevola](https://github.com/marianofevola))

## [v2.10.0](https://github.com/php-amqplib/php-amqplib/tree/v2.10.0) (2019-08-08)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.10.0-rc1...v2.10.0)

**Closed issues:**

- Update API docs [\#721](https://github.com/php-amqplib/php-amqplib/issues/721)

**Merged pull requests:**

- Run toxiproxy based connection tests on travis-ci [\#727](https://github.com/php-amqplib/php-amqplib/pull/727) ([ramunasd](https://github.com/ramunasd))

## [v2.10.0-rc1](https://github.com/php-amqplib/php-amqplib/tree/v2.10.0-rc1) (2019-08-08)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.9.2...v2.10.0-rc1)

**Implemented enhancements:**

- depricated getIO will break heartbeat [\#696](https://github.com/php-amqplib/php-amqplib/issues/696)
- Run travis tests against PHP 7.4 [\#700](https://github.com/php-amqplib/php-amqplib/pull/700) ([ramunasd](https://github.com/ramunasd))
- allow assoc array and generator for connection creation [\#689](https://github.com/php-amqplib/php-amqplib/pull/689) ([black-silence](https://github.com/black-silence))
- getDeliveryTag method for AMQPMessage [\#688](https://github.com/php-amqplib/php-amqplib/pull/688) ([ilyachase](https://github.com/ilyachase))

**Fixed bugs:**

- UNIX only SOCKET\_\* constants trigger E\_NOTICE on Windows [\#723](https://github.com/php-amqplib/php-amqplib/issues/723)
- Fix wrong exception type on failed connect to broker [\#716](https://github.com/php-amqplib/php-amqplib/pull/716) ([ramunasd](https://github.com/ramunasd))

**Closed issues:**

- Heartbeat problem when the consumer consume a message for too long [\#725](https://github.com/php-amqplib/php-amqplib/issues/725)
- Enhance PHPUnit version definitions in composer.json [\#718](https://github.com/php-amqplib/php-amqplib/issues/718)
- Connection timeout disguised as missed server heartbeat [\#713](https://github.com/php-amqplib/php-amqplib/issues/713)
- why php alway quit [\#708](https://github.com/php-amqplib/php-amqplib/issues/708)
- Is Channel access by reference a possibility? [\#707](https://github.com/php-amqplib/php-amqplib/issues/707)
- Broken pipe or closed connection [\#706](https://github.com/php-amqplib/php-amqplib/issues/706)
- Warning for SOCKET\_EWOULDBLOCK not defined [\#705](https://github.com/php-amqplib/php-amqplib/issues/705)
- how to get Consumer Cancel Notify [\#704](https://github.com/php-amqplib/php-amqplib/issues/704)
- Long running producer -- can send messages to queues already declared, but can't declare new queues [\#703](https://github.com/php-amqplib/php-amqplib/issues/703)
- \[Question\] php-amqplib reuse connection [\#702](https://github.com/php-amqplib/php-amqplib/issues/702)
- Enabling heartbeat by default throws PHP Fatal error [\#699](https://github.com/php-amqplib/php-amqplib/issues/699)
- FATAL ERROR: Call to a member function send\_content\(\) on null [\#698](https://github.com/php-amqplib/php-amqplib/issues/698)
- stream\_select\(\): You MUST recompile PHP with a larger value of FD\_SETSIZE [\#693](https://github.com/php-amqplib/php-amqplib/issues/693)
- report error inqueue\_declare [\#692](https://github.com/php-amqplib/php-amqplib/issues/692)
- Catch Them all except: PhpAmqpLib\Exception\AMQPConnectionClosedException [\#691](https://github.com/php-amqplib/php-amqplib/issues/691)
- stream\_socket\_client unable to connect \(Unknown error\) - OpenSSL 1.0 vs 1.1 [\#687](https://github.com/php-amqplib/php-amqplib/issues/687)
- High CPU usage after 2.9.0 release. [\#686](https://github.com/php-amqplib/php-amqplib/issues/686)
- Always allow to set a timeout [\#89](https://github.com/php-amqplib/php-amqplib/issues/89)

**Merged pull requests:**

- Revert changes from \#648 [\#726](https://github.com/php-amqplib/php-amqplib/pull/726) ([lukebakken](https://github.com/lukebakken))
- Wrapper for sockets extension constants [\#724](https://github.com/php-amqplib/php-amqplib/pull/724) ([ramunasd](https://github.com/ramunasd))
- Resolves issue\#718 [\#722](https://github.com/php-amqplib/php-amqplib/pull/722) ([peter279k](https://github.com/peter279k))
- Add github issue templates [\#717](https://github.com/php-amqplib/php-amqplib/pull/717) ([ramunasd](https://github.com/ramunasd))
- PHP information script [\#712](https://github.com/php-amqplib/php-amqplib/pull/712) ([ramunasd](https://github.com/ramunasd))
- Install RabbitMQ package before travis tests [\#711](https://github.com/php-amqplib/php-amqplib/pull/711) ([ramunasd](https://github.com/ramunasd))
- Set minimum PHP version to 5.6 [\#710](https://github.com/php-amqplib/php-amqplib/pull/710) ([ramunasd](https://github.com/ramunasd))
- Added link to \#444 [\#709](https://github.com/php-amqplib/php-amqplib/pull/709) ([Maxim-Mazurok](https://github.com/Maxim-Mazurok))
- fix call to a member function o null when connection was closed [\#701](https://github.com/php-amqplib/php-amqplib/pull/701) ([ramunasd](https://github.com/ramunasd))
- Add connection heartbeat check method [\#697](https://github.com/php-amqplib/php-amqplib/pull/697) ([ramunasd](https://github.com/ramunasd))
- Fix phpdoc [\#690](https://github.com/php-amqplib/php-amqplib/pull/690) ([black-silence](https://github.com/black-silence))
- Adjust PHPDoc for AMQPChannel's "$ticket" parameters [\#685](https://github.com/php-amqplib/php-amqplib/pull/685) ([AegirLeet](https://github.com/AegirLeet))

## [v2.9.2](https://github.com/php-amqplib/php-amqplib/tree/v2.9.2) (2019-04-24)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.9.1...v2.9.2)

**Implemented enhancements:**

- Deprecate access to internal properties and methods [\#673](https://github.com/php-amqplib/php-amqplib/pull/673) ([ramunasd](https://github.com/ramunasd))

**Fixed bugs:**

- Changes in SSL handling breaks bschmitt/laravel-amqp [\#672](https://github.com/php-amqplib/php-amqplib/issues/672)

**Closed issues:**

- stream\_socket\_client\(\): unable to connect to tcp:// [\#682](https://github.com/php-amqplib/php-amqplib/issues/682)
- Demo error [\#680](https://github.com/php-amqplib/php-amqplib/issues/680)
- Broken pipe connection [\#679](https://github.com/php-amqplib/php-amqplib/issues/679)
-  Error Wrong parameters for PhpAmqpLib\Exception\AMQPRuntimeException\(\[string $message \[, long $code \[, Throwable $previous = NULL\]\]\]\) [\#671](https://github.com/php-amqplib/php-amqplib/issues/671)
- stream\_select\(\): unable to select \[4\]: Interrupted system call \(max\_fd=5\) [\#670](https://github.com/php-amqplib/php-amqplib/issues/670)
- AMQP SSL Broken Pipe [\#669](https://github.com/php-amqplib/php-amqplib/issues/669)
- Default heartbeat settings [\#563](https://github.com/php-amqplib/php-amqplib/issues/563)

**Merged pull requests:**

- fix unknown var [\#681](https://github.com/php-amqplib/php-amqplib/pull/681) ([anarbekb](https://github.com/anarbekb))
- Revert default SSL options [\#677](https://github.com/php-amqplib/php-amqplib/pull/677) ([ramunasd](https://github.com/ramunasd))
- fix regression after \#675 due to too early changed flag [\#676](https://github.com/php-amqplib/php-amqplib/pull/676) ([ramunasd](https://github.com/ramunasd))
- Ensure amqp client status is closed that network had been rst [\#675](https://github.com/php-amqplib/php-amqplib/pull/675) ([wjcgithub](https://github.com/wjcgithub))

## [v2.9.1](https://github.com/php-amqplib/php-amqplib/tree/v2.9.1) (2019-03-26)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.9.0...v2.9.1)

**Fixed bugs:**

- Undefined constant SOCKET\_EAGAIN in Windows [\#664](https://github.com/php-amqplib/php-amqplib/issues/664)

**Closed issues:**

- Revert some non-backwards-compatible changes [\#666](https://github.com/php-amqplib/php-amqplib/issues/666)
- getting AMQPTimeoutException on 150+ publishes/second. [\#665](https://github.com/php-amqplib/php-amqplib/issues/665)

**Merged pull requests:**

- Fix undefined constant [\#668](https://github.com/php-amqplib/php-amqplib/pull/668) ([ramunasd](https://github.com/ramunasd))
- Revert argument checking [\#667](https://github.com/php-amqplib/php-amqplib/pull/667) ([lukebakken](https://github.com/lukebakken))

## [v2.9.0](https://github.com/php-amqplib/php-amqplib/tree/v2.9.0) (2019-03-22)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.9.0-rc2...v2.9.0)

**Implemented enhancements:**

- php-amqp AMQPStreamConnection abstraction class constructor sets heartbeat = 0, keepalive = false [\#374](https://github.com/php-amqplib/php-amqplib/issues/374)

**Fixed bugs:**

- Fix wrong error code on stream connection exception [\#663](https://github.com/php-amqplib/php-amqplib/pull/663) ([ramunasd](https://github.com/ramunasd))

## [v2.9.0-rc2](https://github.com/php-amqplib/php-amqplib/tree/v2.9.0-rc2) (2019-03-18)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.9.0-rc1...v2.9.0-rc2)

**Closed issues:**

- Existing error handler is nuked by class StreamIO [\#655](https://github.com/php-amqplib/php-amqplib/issues/655)
- Please, publish your OpenPGP key [\#654](https://github.com/php-amqplib/php-amqplib/issues/654)
- \[2.7,2.8\] StreamIO force protocol "ssl" \(hardcoded\) [\#641](https://github.com/php-amqplib/php-amqplib/issues/641)
- Connection remains  isConnected === true while  AMQPHeartbeatMissedException is thrown [\#627](https://github.com/php-amqplib/php-amqplib/issues/627)
- Duplicate call to AbstractIO::connect\(\) during reconnecting \(AbstractConnection::reconnect\(\)\) [\#626](https://github.com/php-amqplib/php-amqplib/issues/626)
- infinite loop inside StreamIO::write, in case of broken connection [\#624](https://github.com/php-amqplib/php-amqplib/issues/624)
- heartbeat problem on non\_blocking consumers [\#508](https://github.com/php-amqplib/php-amqplib/issues/508)
- isConnected was still true when broken pipe or close connection in channel-\>wait\(\) [\#389](https://github.com/php-amqplib/php-amqplib/issues/389)
- Keepalive and heartbeat on ssl [\#371](https://github.com/php-amqplib/php-amqplib/issues/371)

**Merged pull requests:**

- Allow choosing a different protocol for SSL/TLS [\#661](https://github.com/php-amqplib/php-amqplib/pull/661) ([lukebakken](https://github.com/lukebakken))
- Remove AbstractIO reconnect as it is only used, incorrectly, in one p… [\#660](https://github.com/php-amqplib/php-amqplib/pull/660) ([lukebakken](https://github.com/lukebakken))
- Catch a couple exceptions in select [\#659](https://github.com/php-amqplib/php-amqplib/pull/659) ([lukebakken](https://github.com/lukebakken))
- Throw exception if keepalive cannot be enabled on ssl connections [\#658](https://github.com/php-amqplib/php-amqplib/pull/658) ([ramunasd](https://github.com/ramunasd))

## [v2.9.0-rc1](https://github.com/php-amqplib/php-amqplib/tree/v2.9.0-rc1) (2019-03-08)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.9.0-beta.1...v2.9.0-rc1)

**Merged pull requests:**

- Handle broken pipe or closed connection exceptions [\#653](https://github.com/php-amqplib/php-amqplib/pull/653) ([ramunasd](https://github.com/ramunasd))

## [v2.9.0-beta.1](https://github.com/php-amqplib/php-amqplib/tree/v2.9.0-beta.1) (2019-02-27)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.8.2-rc3...v2.9.0-beta.1)

**Implemented enhancements:**

- Send a specific exception when the vhost doesn't exist [\#343](https://github.com/php-amqplib/php-amqplib/issues/343)

**Fixed bugs:**

- Signals not being handled correctly [\#649](https://github.com/php-amqplib/php-amqplib/issues/649)
- Incorrect list of arguments for AMQPRuntimeException causes fatal error [\#637](https://github.com/php-amqplib/php-amqplib/issues/637)
- Endless loop after broken connection with rabbitmq in AMQPReader::rawread because of zero timeout [\#622](https://github.com/php-amqplib/php-amqplib/issues/622)

**Closed issues:**

- 如何判断 channel-\>basic\_publish 是否成功？ [\#646](https://github.com/php-amqplib/php-amqplib/issues/646)
- 在哪里能设置 x-expires呢，在不修改库代码的情况下 [\#644](https://github.com/php-amqplib/php-amqplib/issues/644)
- Connection is aborted without Exception [\#639](https://github.com/php-amqplib/php-amqplib/issues/639)
- Error: The connection timed out after 3 sec while awaiting incoming data [\#636](https://github.com/php-amqplib/php-amqplib/issues/636)
- What compatibility with Symfony4 [\#635](https://github.com/php-amqplib/php-amqplib/issues/635)
- Workers consuming multiple queues in topic exchange don't always process in parallel prefetch\_count=1 [\#607](https://github.com/php-amqplib/php-amqplib/issues/607)
- Call protected function outside class [\#604](https://github.com/php-amqplib/php-amqplib/issues/604)
- Lazy SSL connection [\#582](https://github.com/php-amqplib/php-amqplib/issues/582)
- Queue declare not timing out [\#561](https://github.com/php-amqplib/php-amqplib/issues/561)
- Error handler relies on locale setting [\#557](https://github.com/php-amqplib/php-amqplib/issues/557)
- Error handling of connection issues [\#548](https://github.com/php-amqplib/php-amqplib/issues/548)
- Right way to use AMQPSocketConnection [\#547](https://github.com/php-amqplib/php-amqplib/issues/547)
- basic\_qos\(\) fails static analysis [\#537](https://github.com/php-amqplib/php-amqplib/issues/537)
- check\_heartbeat in write\(\) at StreamIO [\#507](https://github.com/php-amqplib/php-amqplib/issues/507)
- Return listener not called [\#490](https://github.com/php-amqplib/php-amqplib/issues/490)
- pcntl SIGHUP Consumer restart not working in demo [\#489](https://github.com/php-amqplib/php-amqplib/issues/489)
- Add Roadmap [\#485](https://github.com/php-amqplib/php-amqplib/issues/485)
- Invalid frame type 65 [\#437](https://github.com/php-amqplib/php-amqplib/issues/437)
- Why are we reconnecting in check\_heartbeat method? [\#309](https://github.com/php-amqplib/php-amqplib/issues/309)
- heartbeats and AMQPTimeoutException  [\#249](https://github.com/php-amqplib/php-amqplib/issues/249)
- AMPConnection just sits there [\#248](https://github.com/php-amqplib/php-amqplib/issues/248)
- Repeated Acknowledgements with SetBody\(\) [\#154](https://github.com/php-amqplib/php-amqplib/issues/154)
- StreamIO::read loops infinitely if broker blocks producers [\#148](https://github.com/php-amqplib/php-amqplib/issues/148)
- AMQPConnection can hang in \_\_destruct after a write\(\) failed [\#82](https://github.com/php-amqplib/php-amqplib/issues/82)

**Merged pull requests:**

- Remove workarounds for hhvm, use latest version of scrutinizer tool [\#652](https://github.com/php-amqplib/php-amqplib/pull/652) ([ramunasd](https://github.com/ramunasd))
- Fix signals demo [\#651](https://github.com/php-amqplib/php-amqplib/pull/651) ([ramunasd](https://github.com/ramunasd))
- Fix regression after \#642 [\#650](https://github.com/php-amqplib/php-amqplib/pull/650) ([ramunasd](https://github.com/ramunasd))
- Enable heartbeats by default [\#648](https://github.com/php-amqplib/php-amqplib/pull/648) ([lukebakken](https://github.com/lukebakken))
- Drop support for HHVM [\#647](https://github.com/php-amqplib/php-amqplib/pull/647) ([ramunasd](https://github.com/ramunasd))
- Docker dev environment [\#643](https://github.com/php-amqplib/php-amqplib/pull/643) ([ramunasd](https://github.com/ramunasd))
- Fix channel wait timeouts and endless loops [\#642](https://github.com/php-amqplib/php-amqplib/pull/642) ([ramunasd](https://github.com/ramunasd))
- Add some constant to AMQP exchange [\#640](https://github.com/php-amqplib/php-amqplib/pull/640) ([dream-mo](https://github.com/dream-mo))
- Fixed typo that may cause fatal error in runtime [\#638](https://github.com/php-amqplib/php-amqplib/pull/638) ([FlyingDR](https://github.com/FlyingDR))
- IO improvements [\#634](https://github.com/php-amqplib/php-amqplib/pull/634) ([ramunasd](https://github.com/ramunasd))
- fix isssue with close channel [\#632](https://github.com/php-amqplib/php-amqplib/pull/632) ([kufd](https://github.com/kufd))

## [v2.8.2-rc3](https://github.com/php-amqplib/php-amqplib/tree/v2.8.2-rc3) (2018-12-11)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.8.2-rc2...v2.8.2-rc3)

## [v2.8.2-rc2](https://github.com/php-amqplib/php-amqplib/tree/v2.8.2-rc2) (2018-12-10)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.8.2-rc1...v2.8.2-rc2)

**Implemented enhancements:**

- Test against latest php versions [\#631](https://github.com/php-amqplib/php-amqplib/pull/631) ([ramunasd](https://github.com/ramunasd))
- Allow to specify a timeout for channel operations [\#609](https://github.com/php-amqplib/php-amqplib/pull/609) ([mszabo-wikia](https://github.com/mszabo-wikia))

**Fixed bugs:**

- Error when systemd tries to restart workers since 2.8.0 [\#611](https://github.com/php-amqplib/php-amqplib/issues/611)

**Merged pull requests:**

- Fix wrong exception type on stream timeouts and signals [\#621](https://github.com/php-amqplib/php-amqplib/pull/621) ([ramunasd](https://github.com/ramunasd))

## [v2.8.2-rc1](https://github.com/php-amqplib/php-amqplib/tree/v2.8.2-rc1) (2018-11-29)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.8.1...v2.8.2-rc1)

**Fixed bugs:**

- Fix and add test for signal handling with PCNTL extension [\#630](https://github.com/php-amqplib/php-amqplib/pull/630) ([Shivox](https://github.com/Shivox))

**Closed issues:**

- How do I listen to all queues [\#629](https://github.com/php-amqplib/php-amqplib/issues/629)
- Broken pipe or closed connection [\#628](https://github.com/php-amqplib/php-amqplib/issues/628)
- yii queue/listen No "message\_id" property [\#625](https://github.com/php-amqplib/php-amqplib/issues/625)
- Long phpamqplib.DEBUG: Queue message processed logs before throwing fwrite [\#623](https://github.com/php-amqplib/php-amqplib/issues/623)
- Undefined constant SOCKET\_EAGAIN in Windows [\#619](https://github.com/php-amqplib/php-amqplib/issues/619)

**Merged pull requests:**

- Fix undefined constant SOCKET\_EAGAIN in Windows [\#620](https://github.com/php-amqplib/php-amqplib/pull/620) ([MaxwellZY](https://github.com/MaxwellZY))

## [v2.8.1](https://github.com/php-amqplib/php-amqplib/tree/v2.8.1) (2018-11-13)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.8.1-rc3...v2.8.1)

## [v2.8.1-rc3](https://github.com/php-amqplib/php-amqplib/tree/v2.8.1-rc3) (2018-11-07)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.8.1-rc2...v2.8.1-rc3)

**Fixed bugs:**

- fwrite\(\): send of 3728 bytes failed with errno=11 Resource temporarily unavailable [\#613](https://github.com/php-amqplib/php-amqplib/issues/613)

**Closed issues:**

- calling check\_heartbeat causes connection to close [\#617](https://github.com/php-amqplib/php-amqplib/issues/617)
- message not received by the server but no exception thrown [\#595](https://github.com/php-amqplib/php-amqplib/issues/595)

**Merged pull requests:**

- Restore code that sets last\_read [\#618](https://github.com/php-amqplib/php-amqplib/pull/618) ([lukebakken](https://github.com/lukebakken))

## [v2.8.1-rc2](https://github.com/php-amqplib/php-amqplib/tree/v2.8.1-rc2) (2018-11-02)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.8.1-rc1...v2.8.1-rc2)

**Closed issues:**

- AMQPStreamConnection $connection\_timeout - milliseconds or seconds? [\#616](https://github.com/php-amqplib/php-amqplib/issues/616)

**Merged pull requests:**

- Parse error string to determine error number instead of using errno [\#615](https://github.com/php-amqplib/php-amqplib/pull/615) ([davidgreisler](https://github.com/davidgreisler))

## [v2.8.1-rc1](https://github.com/php-amqplib/php-amqplib/tree/v2.8.1-rc1) (2018-10-30)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.8.0...v2.8.1-rc1)

**Fixed bugs:**

- ext-sockets required since 2.8.0 [\#608](https://github.com/php-amqplib/php-amqplib/issues/608)
- Fixed restoring previous error handler in StreamIO [\#612](https://github.com/php-amqplib/php-amqplib/pull/612) ([cezarystepkowski](https://github.com/cezarystepkowski))
- Move ext-sockets from "suggest" to "require" [\#610](https://github.com/php-amqplib/php-amqplib/pull/610) ([lukebakken](https://github.com/lukebakken))

**Closed issues:**

- Getting really often "Connection reset by peer" [\#546](https://github.com/php-amqplib/php-amqplib/issues/546)

## [v2.8.0](https://github.com/php-amqplib/php-amqplib/tree/v2.8.0) (2018-10-23)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.7.2.1...v2.8.0)

**Closed issues:**

- Feature Request: Allow overriding of LIBRARY\_PROPERTIES [\#603](https://github.com/php-amqplib/php-amqplib/issues/603)

**Merged pull requests:**

- Add getLibraryProperties abstract connection method and test [\#606](https://github.com/php-amqplib/php-amqplib/pull/606) ([madrussa](https://github.com/madrussa))
- Fix potential indefinite wait [\#602](https://github.com/php-amqplib/php-amqplib/pull/602) ([lukebakken](https://github.com/lukebakken))
- fix the logical error [\#601](https://github.com/php-amqplib/php-amqplib/pull/601) ([aisuhua](https://github.com/aisuhua))
- Use specific exceptions instead of general AMQPRuntimeException [\#600](https://github.com/php-amqplib/php-amqplib/pull/600) ([ondrej-bouda](https://github.com/ondrej-bouda))

## [v2.7.2.1](https://github.com/php-amqplib/php-amqplib/tree/v2.7.2.1) (2018-10-17)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.8.0-rc1...v2.7.2.1)

**Closed issues:**

- When heartbeats parameter is greater than 0 [\#352](https://github.com/php-amqplib/php-amqplib/issues/352)

## [v2.8.0-rc1](https://github.com/php-amqplib/php-amqplib/tree/v2.8.0-rc1) (2018-10-11)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.7.3...v2.8.0-rc1)

**Closed issues:**

- "Server nack'ed unknown delivery\_tag" when using batch\_basic\_publish [\#597](https://github.com/php-amqplib/php-amqplib/issues/597)
- fwrite: errno=11 in StreamIO [\#596](https://github.com/php-amqplib/php-amqplib/issues/596)
- Use swoole to generate multi-process channel errors [\#592](https://github.com/php-amqplib/php-amqplib/issues/592)
- Connecting RMQ with multiple host connection  [\#588](https://github.com/php-amqplib/php-amqplib/issues/588)
- where is the function "AMQPStreamConnection::create\_connection\(\)" [\#586](https://github.com/php-amqplib/php-amqplib/issues/586)
- RPC server not sending reply down the wire [\#585](https://github.com/php-amqplib/php-amqplib/issues/585)
- Please add support for AMQP 1.0 [\#583](https://github.com/php-amqplib/php-amqplib/issues/583)
- Connecting to Red Hat JBOSS [\#580](https://github.com/php-amqplib/php-amqplib/issues/580)
- Consuming message coming in truncated [\#579](https://github.com/php-amqplib/php-amqplib/issues/579)
- can't throw fwrite\(\) error immediately [\#578](https://github.com/php-amqplib/php-amqplib/issues/578)
- Can't reuse AMQPMessage object with new properties [\#576](https://github.com/php-amqplib/php-amqplib/issues/576)
- Invalid frame type 65 [\#572](https://github.com/php-amqplib/php-amqplib/issues/572)
- The set\_nack\_handle  can not be triggered correctly. [\#571](https://github.com/php-amqplib/php-amqplib/issues/571)
- channel-\>wait\(\) with timeout make memory leak [\#566](https://github.com/php-amqplib/php-amqplib/issues/566)
- SOCKS Proxy between RMQ and client [\#558](https://github.com/php-amqplib/php-amqplib/issues/558)
- Version 2.7 connects as 2.6 [\#555](https://github.com/php-amqplib/php-amqplib/issues/555)
- Update minimum php version in composer.json [\#543](https://github.com/php-amqplib/php-amqplib/issues/543)
- StreamIO can wait for data indefinitely [\#416](https://github.com/php-amqplib/php-amqplib/issues/416)
- Releasing connection reference too early in a channel leads to a segmentation fault [\#415](https://github.com/php-amqplib/php-amqplib/issues/415)
- StreamConnection does not time out [\#408](https://github.com/php-amqplib/php-amqplib/issues/408)
- $this-\>debug can be null in AbstractConnection.php [\#386](https://github.com/php-amqplib/php-amqplib/issues/386)
- Read and write to multiple queues within one script [\#293](https://github.com/php-amqplib/php-amqplib/issues/293)
- lazy channels [\#291](https://github.com/php-amqplib/php-amqplib/issues/291)
- decode\(\) method not defined [\#160](https://github.com/php-amqplib/php-amqplib/issues/160)

**Merged pull requests:**

- Use errno instead of error strings [\#599](https://github.com/php-amqplib/php-amqplib/pull/599) ([marek-obuchowicz](https://github.com/marek-obuchowicz))
- Corrected typo and comment alignment in demo/amqp\_consumer\_exclusive.php [\#591](https://github.com/php-amqplib/php-amqplib/pull/591) ([lkorczewski](https://github.com/lkorczewski))
- Corrected typos in demo/amqp\_publisher\_exclusive.php [\#590](https://github.com/php-amqplib/php-amqplib/pull/590) ([lkorczewski](https://github.com/lkorczewski))
- Fix heartbeat-check if pcntl is unavailable [\#584](https://github.com/php-amqplib/php-amqplib/pull/584) ([srebbsrebb](https://github.com/srebbsrebb))
- don't throw an exception in an error handler [\#581](https://github.com/php-amqplib/php-amqplib/pull/581) ([deweller](https://github.com/deweller))
- Cleanup serialized\_properties on property set [\#577](https://github.com/php-amqplib/php-amqplib/pull/577) ([p-golovin](https://github.com/p-golovin))
- Annotate at @throws \ErrorException at AbstractChannel::wait [\#575](https://github.com/php-amqplib/php-amqplib/pull/575) ([nohponex](https://github.com/nohponex))
- Structuring tests [\#574](https://github.com/php-amqplib/php-amqplib/pull/574) ([programarivm](https://github.com/programarivm))
- Test with php 5.3 and 7.2 [\#569](https://github.com/php-amqplib/php-amqplib/pull/569) ([snapshotpl](https://github.com/snapshotpl))
- Add extended datatype for bytes [\#568](https://github.com/php-amqplib/php-amqplib/pull/568) ([masell](https://github.com/masell))
- Fwrite \ErrorException not being thrown to the top function call when doing basic\_publish [\#564](https://github.com/php-amqplib/php-amqplib/pull/564) ([dp-indrak](https://github.com/dp-indrak))
- Introduce a method to create connection from multiple hosts. [\#562](https://github.com/php-amqplib/php-amqplib/pull/562) ([hairyhum](https://github.com/hairyhum))
- Throw exception on missed heartbeat [\#559](https://github.com/php-amqplib/php-amqplib/pull/559) ([hairyhum](https://github.com/hairyhum))

## [v2.7.3](https://github.com/php-amqplib/php-amqplib/tree/v2.7.3) (2018-04-30)

[Full Changelog](https://github.com/php-amqplib/php-amqplib/compare/v2.7.2...v2.7.3)

**Closed issues:**

- stream\_select\(\) ErrorException FD\_SETSIZE [\#552](https://github.com/php-amqplib/php-amqplib/issues/552)
- Whoops, looks like something went wrong. \(1/1\) ErrorException getimagesize\(\): send of 18 bytes failed with errno=104 Connection reset by peer [\#551](https://github.com/php-amqplib/php-amqplib/issues/551)
- no-local? [\#550](https://github.com/php-amqplib/php-amqplib/issues/550)
- Can php-amqplib consumer work on a web page? [\#549](https://github.com/php-amqplib/php-amqplib/issues/549)
- Functional tests fail after upgrading to 2.7.1 and 2.7.2 [\#545](https://github.com/php-amqplib/php-amqplib/issues/545)
- fwrite failure / not sure how to debug further [\#544](https://github.com/php-amqplib/php-amqplib/issues/544)

# Previous releases

## 2.7.2 - 2018-02-11

[GitHub Milestone](https://github.com/php-amqplib/php-amqplib/milestone/5?closed=1)

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


\* *This Changelog was automatically generated by [github_changelog_generator](https://github.com/github-changelog-generator/github-changelog-generator)*

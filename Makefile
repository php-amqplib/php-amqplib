.PHONY: test
test:
	./vendor/bin/phpunit
.PHONY: docs
docs:
	wget -qN https://github.com/phpDocumentor/phpDocumentor/releases/download/v3.3.0/phpDocumentor.phar
	rm -rf ./docs/*
	php -d error_reporting=0 ./phpDocumentor.phar run -v --force --defaultpackagename=PhpAmqpLib --title='php-amqplib' -d ./PhpAmqpLib -t ./docs
.PHONY: benchmark
benchmark:
	@echo "Publishing 10k messages with 1KB of content:"
	php benchmark/producer.php 10000
	@echo "Consuming:"
	php benchmark/consumer.php
	@echo "Stream produce 1k:"
	php benchmark/stream_tmp_produce.php 1000
	@echo "Socket produce 1k:"
	php benchmark/socket_tmp_produce.php 1000

.PHONY: test
test:
	./vendor/bin/phpunit
.PHONY: docs
docs:
	wget -qN https://github.com/phpDocumentor/phpDocumentor2/releases/download/v2.9.1/phpDocumentor.phar
	rm -rf ./docs/*
	php -d error_reporting=0 ./phpDocumentor.phar run -q --force --template=clean --defaultpackagename=PhpAmqpLib --title='php-amqplib' -d ./PhpAmqpLib -t ./docs
.PHONY: benchmark
benchmark:
	@echo "Publishing 4000 msgs with 1KB of content:"
	php benchmark/producer.php 4000
	@echo "Consuming 4000:"
	php benchmark/consumer.php
	@echo "Stream produce 100:"
	php benchmark/stream_tmp_produce.php 100
	@echo "Socket produce 100:"
	php benchmark/socket_tmp_produce.php 100

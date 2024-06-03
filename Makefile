DOCKER_FRESH ?= false

TOXIPROXY_HOST ?= localhost
TOXIPROXY_AMQP_TARGET ?= php-amqplib-rabbitmq
TOXIPROXY_AMQP_PORT ?= 5673

.PHONY: test
test:
	TOXIPROXY_HOST=$(TOXIPROXY_HOST) \
	TOXIPROXY_AMQP_TARGET=$(TOXIPROXY_AMQP_TARGET) \
	TOXIPROXY_AMQP_PORT=$(TOXIPROXY_AMQP_PORT) \
		$(CURDIR)/vendor/bin/phpunit

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

.PHONY: docker-test-env
docker-test-env:
ifeq ($(DOCKER_FRESH),true)
	docker build --pull --no-cache --tag=php-amqplib-php:latest $(CURDIR)/docker/php
	docker compose build --no-cache --pull
	docker compose up --pull always --detach
else
	docker build --tag=php-amqplib-php:latest $(CURDIR)/docker/php
	docker compose up --detach
endif

.PHONY: docker-test
docker-test:
	docker run --env-file $(CURDIR)/test.env --network php-amqplib_default \
		--volume $(CURDIR):/src --workdir /src \
		--user "$$(id -u):$$(id -g)" php-amqplib-php:latest \
			/bin/sh -c '/usr/bin/composer install && ./vendor/bin/phpunit'

.PHONY: clean
clean:
	git clean -xffd

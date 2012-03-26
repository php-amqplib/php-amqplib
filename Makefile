VENDOR = vendor
COMPONENTS = $(VENDOR)/symfony/Symfony/Component
CLASS_LOADER = $(COMPONENTS)/ClassLoader/UniversalClassLoader.php

all: $(CLASS_LOADER)

$(CLASS_LOADER):
	git submodule init
	git submodule update

test: all
	phpunit


benchmark: all
	@echo "Publishing 4000 msgs with 1KB of content:"
	php benchmark/producer.php 4000
	@echo "Consuming 4000:"
	php benchmark/consumer.php
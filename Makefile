test:
	phpunit

benchmark:
	@echo "Publishing 4000 msgs with 1KB of content:"
	php benchmark/producer.php 4000
	@echo "Consuming 4000:"
	php benchmark/consumer.php

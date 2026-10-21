FROM php:8.0-cli

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/2.12.1/install-php-extensions /usr/local/bin/
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN install-php-extensions sockets pcntl zip xdebug
RUN echo 'memory_limit = 1G' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini
RUN rm /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /src

FROM php:5.6-cli

RUN apt update && \
    apt -qy install git unzip zlib1g-dev && \
    docker-php-ext-install sockets pcntl zip

WORKDIR /src
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    mv composer.phar /usr/local/bin/composer


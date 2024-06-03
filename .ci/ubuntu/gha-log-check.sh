#!/bin/bash

set -o errexit
set -o pipefail
set -o xtrace
set -o nounset

readonly docker_name_prefix='php-amqplib'

declare -r rabbitmq_docker_name="$docker_name_prefix-rabbitmq"

if docker logs "$rabbitmq_docker_name" | grep -iF inet_error
then
    echo '[ERROR] found inet_error in RabbitMQ logs' 1>&2
    exit 1
fi

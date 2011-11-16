#!/bin/sh

# guest:guest has full access to /

sudo rabbitmqctl add_vhost /
sudo rabbitmqctl add_user guest guest
sudo rabbitmqctl set_permissions -p / guest ".*" ".*" ".*"


# amqp_gem:amqp_gem_password has full access to amqp_gem_testbed

sudo rabbitmqctl add_vhost phpamqplib_testbed
sudo rabbitmqctl add_user phpamqplib phpamqplib_password
sudo rabbitmqctl set_permissions -p phpamqplib_testbed phpamqplib ".*" ".*" ".*"

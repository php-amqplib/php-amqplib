#!/bin/sh

# phpamqplib:phpamqplib_password has full access to phpamqplib_testbed

sudo rabbitmqctl add_vhost phpamqplib_testbed
sudo rabbitmqctl add_user phpamqplib phpamqplib_password
sudo rabbitmqctl set_permissions -p phpamqplib_testbed phpamqplib ".*" ".*" ".*"

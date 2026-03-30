#!/bin/bash

# Data directories
mkdir -p _DATA/apache/downloads
mkdir -p _DATA/apache/public
mkdir -p _DATA/mysql

# Logs directories
mkdir -p _LOGS/apache/logs
mkdir -p _LOGS/apache/laravel_logs

# SSO directories
mkdir -p _SSO/simplesaml/config
mkdir -p _SSO/simplesaml/metadata
mkdir -p _SSO/simplesaml/cert



# Run the stack
sudo docker network create --subnet=172.18.0.0/24 external
sudo docker-compose down
sudo docker-compose up -d --build

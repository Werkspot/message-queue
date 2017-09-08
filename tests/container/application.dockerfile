# This file is used to automatically build the container used in docker-compose.yml
# For more details check https://hub.docker.com/r/werkspot/message-queue/builds/
FROM php:7.1-alpine

RUN docker-php-ext-configure pcntl
RUN docker-php-ext-configure bcmath
RUN docker-php-ext-install pcntl
RUN docker-php-ext-install bcmath

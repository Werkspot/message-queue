FROM php:7.1-alpine

RUN docker-php-ext-configure pcntl
RUN docker-php-ext-configure bcmath
RUN docker-php-ext-install pcntl
RUN docker-php-ext-install bcmath

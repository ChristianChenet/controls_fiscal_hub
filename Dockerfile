FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    zip \
    && docker-php-ext-install pdo_pgsql pgsql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY ./app /var/www/html

RUN mkdir -p /var/www/html/storage/certificates/runtime \
    /var/www/html/storage/xmls \
    /var/www/html/storage/exports \
    /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/storage

COPY ./docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
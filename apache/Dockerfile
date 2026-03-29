FROM php:8.1.21-apache

# MySQL deps
RUN docker-php-ext-install pdo_mysql

# SAML deps
RUN apt-get -y update \
    && apt-get install -y libicu-dev \
    && docker-php-ext-install intl

# Apache
RUN a2enmod rewrite

# Install composer and its dependencies
RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer



# SETUP WEB APP
# =============
WORKDIR /var/www/html

# Configuration
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf
COPY ports.conf /etc/apache2/ports.conf


# Web application
COPY --chown=1000:1000 app /var/www/html
RUN ln -sfn /var/www/html/storage/app/public /var/www/html/public/upload

# Install vendor dependencies
RUN composer install --no-dev --optimize-autoloader


# SimpleSAML symlink
RUN ln -sfn /var/www/html/vendor/simplesamlphp/simplesamlphp/public /var/www/html/public/simplesaml


USER 1000:1000

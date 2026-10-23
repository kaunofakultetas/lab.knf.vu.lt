FROM php:8.4.27-apache

# MySQL deps
RUN docker-php-ext-install pdo_mysql

# SAML deps
RUN apt-get -y update \
    && apt-get install -y libicu-dev \
    && docker-php-ext-install intl

# PHP 8.4 reports the implicitly nullable parameters of the locked
# Laravel 10 and Symfony 6 code as deprecated, some of them before
# Laravel's own error handler takes over; keep those notices out of
# the pages, as PHP 8.1 had none to show
RUN echo 'error_reporting = E_ALL & ~E_DEPRECATED' > "$PHP_INI_DIR/conf.d/error-reporting.ini"

# Apache
RUN a2enmod rewrite remoteip

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

# Install vendor dependencies, exactly the locked versions: nette/schema
# and nette/utils, which only Laravel's unused Markdown support pulls in,
# cap PHP below 8.3, so only that upper bound is ignored
RUN composer install --no-dev --optimize-autoloader --ignore-platform-req=php+


# SimpleSAML symlink
RUN ln -sfn /var/www/html/vendor/simplesamlphp/simplesamlphp/public /var/www/html/public/simplesaml


USER 1000:1000

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

# Without a php.ini, PHP names its exact version in an X-Powered-By
# header on every page
RUN echo 'expose_php = Off' > "$PHP_INI_DIR/conf.d/expose-php.ini"

# Without a php.ini, PHP also fills $_SERVER['argv'] from a web
# request's query string, which Laravel 10.6 reads to pick its
# environment (CVE-2024-52301); web requests take no arguments
RUN echo 'register_argc_argv = Off' > "$PHP_INI_DIR/conf.d/register-argc-argv.ini"

# SimpleSAMLphp keeps the VU login in a PHP session file and tells
# sso.vu.lt about a sign-out only while the file is there; PHP's own
# default deletes a session file 24 minutes after its last write, so
# keep one as long as a lab session lasts, 36 hours
RUN echo 'session.gc_maxlifetime = 129600' > "$PHP_INI_DIR/conf.d/session.ini"

# Apache
RUN a2enmod rewrite remoteip

# Install composer and its dependencies; Composer at an exact version,
# so that every build installs the vendor the same way
RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer



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

# SimpleSAMLphp's sessions, a folder docker-compose mounts from the
# host, so that a redeploy keeps them (session.phpsession.savepath)
RUN mkdir -p /var/www/simplesamlphp-sessions && chown 1000:1000 /var/www/simplesamlphp-sessions


USER 1000:1000

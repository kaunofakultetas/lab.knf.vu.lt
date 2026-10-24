FROM php:8.4.27-apache

# MySQL deps
RUN docker-php-ext-install pdo_mysql

# SAML deps: intl, and bcmath for SimpleSAMLphp's XML library
RUN apt-get -y update \
    && apt-get install -y libicu-dev \
    && docker-php-ext-install intl bcmath

# Without a php.ini, PHP prints its notices into the page; keep the
# deprecation notices a newer PHP raises about older library code out
# of the pages, some of which come before Laravel's own error handler
# takes over. Known bug LAB-91: display_errors stays at PHP's default,
# on, so the pages Laravel does not handle, SimpleSAMLphp's, still
# print PHP's warnings, the server's file paths among them; fix:
# display_errors = Off in an ini file here, PHP still logging them to
# Apache's error log
RUN echo 'error_reporting = E_ALL & ~E_DEPRECATED' > "$PHP_INI_DIR/conf.d/error-reporting.ini"

# Without a php.ini, PHP names its exact version in an X-Powered-By
# header on every page
RUN echo 'expose_php = Off' > "$PHP_INI_DIR/conf.d/expose-php.ini"

# Without a php.ini, PHP also fills $_SERVER['argv'] from a web
# request's query string, which Laravel before 10.48.23 read to pick
# its environment (CVE-2024-52301); web requests take no arguments
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

# Install vendor dependencies, exactly the locked versions
RUN composer install --no-dev --optimize-autoloader


# SimpleSAML symlink
RUN ln -sfn /var/www/html/vendor/simplesamlphp/simplesamlphp/public /var/www/html/public/simplesaml

# SimpleSAMLphp's sessions, a folder docker-compose mounts from the
# host, so that a redeploy keeps them (session.phpsession.savepath)
RUN mkdir -p /var/www/simplesamlphp-sessions && chown 1000:1000 /var/www/simplesamlphp-sessions


USER 1000:1000

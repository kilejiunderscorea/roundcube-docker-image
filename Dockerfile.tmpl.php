<?php
$var = getopt('', ['version:', 'dockerfile:']);
$isApacheImage = (end(explode('/', $var['dockerfile'])) === 'apache');
$RoundcubeVer = reset(explode('-', $var['version']))
?># AUTOMATICALLY GENERATED
# DO NOT EDIT THIS FILE DIRECTLY, USE /Dockerfile.tmpl.php

<? if ($isApacheImage) {
?># https://github.com/docker-library/php/blob/master/7.0/apache/Dockerfile
FROM php:7.0-apache
<? } else {
?># https://github.com/docker-library/php/blob/master/7.0/fpm/alpine/Dockerfile
FROM php:7.0-fpm-alpine
<? } ?>

MAINTAINER Instrumentisto Team <developer@instrumentisto.com>


# Install s6-overlay
RUN curl -L -o /tmp/s6-overlay.tar.gz \
         https://github.com/just-containers/s6-overlay/releases/download/v1.19.1.1/s6-overlay-amd64.tar.gz \
 && tar -xzf /tmp/s6-overlay.tar.gz -C / \
 && rm -rf /tmp/s6-overlay.tar.gz

ENV S6_KEEP_ENV=1 \
    S6_CMD_WAIT_FOR_SERVICES=1


# Install required libraries and PHP extensions
<? if ($isApacheImage) { ?>
RUN apt-get update \
 && apt-get upgrade -y \
 && update-ca-certificates \
 && apt-get install -y --no-install-recommends \
            rsyslog \
 && apt-get install -y --no-install-recommends \
            libpq5 libodbc1 libsybdb5 \
            libaspell15 \
            libicu52 \
            libldap-2.4-2 libsasl2-2 \

 && buildDeps=" \
      libpq-dev unixodbc-dev freetds-dev \
      libpspell-dev \
      libicu-dev \
      libldap2-dev \
      libzip-dev \
    " \
 && apt-get install -y $buildDeps --no-install-recommends \

 && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu \
 && docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr \
 && docker-php-ext-configure pdo_dblib --with-libdir=lib/x86_64-linux-gnu \
 && docker-php-ext-install \
           exif \
           intl \
           ldap \
           opcache \
           pdo_mysql pdo_pgsql pdo_odbc pdo_dblib \
           pspell \
           sockets \
           zip \

 && a2enmod expires \
            headers \
            rewrite \

 && apt-get purge -y --auto-remove \
                  -o APT::AutoRemove::RecommendsImportant=false \
                  $buildDeps \
 && rm -rf /var/lib/apt/lists/*
<? } else { ?>
RUN apk update \
 && apk upgrade \
 && update-ca-certificates \
 && apk add --no-cache --virtual .php-ext-deps \
        libpq unixodbc freetds \
        aspell-libs \
        icu-libs \
        libldap \
        zlib \

 && apk add --no-cache --virtual .build-deps \
        postgresql-dev unixodbc-dev freetds-dev \
        aspell-dev \
        icu-dev \
        openldap-dev \
        zlib-dev \

 && docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr \
 && docker-php-ext-install \
           exif \
           intl \
           ldap \
           opcache \
           pdo_mysql pdo_pgsql pdo_odbc pdo_dblib \
           pspell \
           sockets \
           zip \

 && apk del .build-deps \
 && rm -rf /var/cache/apk/*
<? } ?>


# Install Roundcube
RUN curl -L -o /tmp/roundcube.tar.gz \
         https://github.com/roundcube/roundcubemail/releases/download/<?= $RoundcubeVer; ?>/roundcubemail-<?= $RoundcubeVer; ?>.tar.gz \
 && tar -xzf /tmp/roundcube.tar.gz -C /tmp/ \
 && rm -rf /app \
 && mv /tmp/roundcubemail-<?= $RoundcubeVer; ?> /app \

 # Install Composer to resolve Roundcube dependencies
 && curl -L -o /tmp/composer-setup.php \
          https://getcomposer.org/installer \
 && curl -L -o /tmp/composer-setup.sig \
          https://composer.github.io/installer.sig \
 && php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { echo 'Invalid installer' . PHP_EOL; exit(1); }" \
 && php /tmp/composer-setup.php --install-dir=/tmp --filename=composer \

 # Resolve Roudcube dependencies
<? if ($isApacheImage) { ?>
 && apt-get update \
 && composerDeps="git" \
 && apt-get install -y $composerDeps --no-install-recommends \
<? } else { ?>
 && apk add --update --no-cache --virtual .composer-deps \
        git \
<? } ?>
 && mv /app/composer.json-dist /app/composer.json \
 && cd /app \
 && /tmp/composer install --no-dev --optimize-autoloader \

 # Make default Roudcube configuration log to syslog
 && sed -i -r 's/^([^\s]{9}log_driver[^\s]{2} =) [^\s]+$/\1 "syslog";/g' \
        /app/config/defaults.inc.php \

<? if ($isApacheImage) { ?>
 # Fix Roundcube .htaccess for PHP7
 && sed -i -r 's/^(<IfModule mod)_php5/\1_php7/g' \
        /app/.htaccess \
 && sed -i -r 's/^(php_flag[ ]+(register_globals|magic_quotes|suhosin))/#\1/g' \
        /app/.htaccess \

<? } ?>
 # Setup serve directories
 && cd /app \
 && ln -sn ./public_html /app/html \
 && rm -rf /var/www \
 && ln -s /app /var/www \

 # Set correct owner
 && chown -R www-data:www-data /app /var/www \

<? if ($isApacheImage) { ?>
 && apt-get purge -y --auto-remove \
                  -o APT::AutoRemove::RecommendsImportant=false \
                  $composerDeps \
 && rm -rf /var/lib/apt/lists/* \
<? } else { ?>
 && apk del .composer-deps \
 && rm -rf /var/cache/apk/* \
<? } ?>
           /tmp/*


# Install configurations
COPY rootfs /

# Fix executable rights
RUN chmod +x /etc/services.d/*/run \
             /start.sh \

 # Prepare directory for SQLite database
 && mkdir -p /var/db \
 && chown -R www-data:www-data /var/db

ENV PHP_OPCACHE_REVALIDATION=0 \
    SHARE_APP=0


WORKDIR /var/www

ENTRYPOINT ["/init", "/start.sh"]

<? if ($isApacheImage) { ?>
CMD ["apache2-foreground"]
<? } else { ?>
CMD ["php-fpm"]
<? } ?>

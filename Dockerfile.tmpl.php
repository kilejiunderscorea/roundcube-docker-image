<?php
$var = getopt('', ['version:', 'dockerfile:']);
$isApacheImage = (end(explode('/', $var['dockerfile'])) === 'apache');
$RoundcubeVer = reset(explode('-', $var['version']));
$isMinorVerLt3 = (intval(explode('.', $RoundcubeVer)[1]) < 3);
?>
# AUTOMATICALLY GENERATED
# DO NOT EDIT THIS FILE DIRECTLY, USE /Dockerfile.tmpl.php

# https://hub.docker.com/_/php
<? if ($isApacheImage) { ?>
FROM php:7.1-apache
<? } else { ?>
FROM php:7.1-fpm-alpine
<? } ?>

MAINTAINER Instrumentisto Team <developer@instrumentisto.com>


# Install s6-overlay
RUN curl -fL -o /tmp/s6-overlay.tar.gz \
         https://github.com/just-containers/s6-overlay/releases/download/v1.21.2.0/s6-overlay-amd64.tar.gz \
 && tar -xzf /tmp/s6-overlay.tar.gz -C / \
 && rm -rf /tmp/*

ENV S6_KEEP_ENV=1 \
    S6_CMD_WAIT_FOR_SERVICES=1


# Install required libraries and PHP extensions
<? if ($isApacheImage) { ?>
RUN apt-get update \
 && apt-get upgrade -y \
<? } else { ?>
RUN apk update \
 && apk upgrade \
<? } ?>
 && update-ca-certificates \
<? if ($isApacheImage) { ?>
 && apt-get install -y --no-install-recommends --no-install-suggests \
            inetutils-syslogd \
 && apt-get install -y --no-install-recommends --no-install-suggests \
            libpq5 libodbc1 libsybdb5 \
            libaspell15 \
            libicu52 \
            libldap-2.4-2 libsasl2-2 \
<? if (!$isMinorVerLt3) { ?>
            libjpeg62-turbo libpng12-0 libfreetype6 \
<? } ?>
<? } else { ?>
 && apk add --no-cache --virtual .php-ext-deps \
        libpq unixodbc freetds \
        aspell-libs \
        icu-libs \
        libldap \
<? if (!$isMinorVerLt3) { ?>
        libjpeg-turbo libpng freetype \
<? } ?>
        zlib \
<? } ?>
    \
<? if ($isApacheImage) { ?>
 && buildDeps=" \
      libpq-dev unixodbc-dev freetds-dev \
      libpspell-dev \
      libicu-dev \
      libldap2-dev \
<? if (!$isMinorVerLt3) { ?>
      libjpeg62-turbo-dev libpng12-dev libfreetype6-dev \
<? } ?>
      libzip-dev \
    " \
 && apt-get install -y --no-install-recommends --no-install-suggests \
            $buildDeps \
<? } else { ?>
 && apk add --no-cache --virtual .build-deps \
        postgresql-dev unixodbc-dev freetds-dev \
        aspell-dev \
        icu-dev \
        openldap-dev \
<? if (!$isMinorVerLt3) { ?>
        libjpeg-turbo-dev libpng-dev freetype-dev \
<? } ?>
        zlib-dev \
<? } ?>
    \
<? if ($isApacheImage) { ?>
 && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu \
 && docker-php-ext-configure pdo_dblib --with-libdir=lib/x86_64-linux-gnu \
<? } ?>
<? if (!$isMinorVerLt3) { ?>
<? if ($isApacheImage) { ?>
 && docker-php-ext-configure gd --with-jpeg-dir=/usr/include/ \
                                --with-freetype-dir=/usr/include/ \
<? } else { ?>
 && docker-php-ext-configure gd --with-jpeg-dir=/usr/include/ \
                                --with-png-dir=/usr/include/ \
                                --with-freetype-dir=/usr/include/ \
<? } ?>
<? } ?>
 && docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr \
 && docker-php-ext-install \
           exif \
<? if (!$isMinorVerLt3) { ?>
           gd \
<? } ?>
           intl \
           ldap \
           opcache \
           pdo_mysql pdo_pgsql pdo_odbc pdo_dblib \
           pspell \
           sockets \
           zip \
<? if ($isApacheImage) { ?>
    \
 && a2enmod expires \
            headers \
            rewrite \
<? } ?>
    \
 # Cleanup stuff
<? if ($isApacheImage) { ?>
 && apt-get purge -y --auto-remove \
                  -o APT::AutoRemove::RecommendsImportant=false \
            $buildDeps \
 && rm -rf /var/lib/apt/lists/*
<? } else { ?>
 && apk del .build-deps \
 && rm -rf /var/cache/apk/*
<? } ?>


# Install Roundcube
RUN curl -fL -o /tmp/roundcube.tar.gz \
         https://github.com/roundcube/roundcubemail/releases/download/<?= $RoundcubeVer; ?>/roundcubemail-<?= $RoundcubeVer; ?>.tar.gz \
 && tar -xzf /tmp/roundcube.tar.gz -C /tmp/ \
 && rm -rf /app \
 && mv /tmp/roundcubemail-<?= $RoundcubeVer; ?> /app \
    \
 # Install Composer to resolve Roundcube dependencies
 && curl -fL -o /tmp/composer-setup.php \
          https://getcomposer.org/installer \
 && curl -fL -o /tmp/composer-setup.sig \
          https://composer.github.io/installer.sig \
 && php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { echo 'Invalid installer' . PHP_EOL; exit(1); }" \
 && php /tmp/composer-setup.php --install-dir=/tmp --filename=composer \
    \
 # Install tools for building
<? if ($isApacheImage) { ?>
 && apt-get update \
 && toolDeps=" \
        git \
        unzip \
    " \
 && apt-get install -y --no-install-recommends --no-install-suggests \
            $toolDeps \
<? } else { ?>
 && apk add --update --no-cache --virtual .tool-deps \
        git \
        file \
<? } ?>
    \
 # Resolve Roudcube Composer dependencies
 && mv /app/composer.json-dist /app/composer.json \
 && cd /app \
 && /tmp/composer install --no-dev --optimize-autoloader --no-progress \
<? if (!$isMinorVerLt3) { ?>
    \
 # Resolve Roudcube JS dependencies
 && /app/bin/install-jsdeps.sh \
<? } ?>
    \
 # Make default Roudcube configuration log to syslog
 && sed -i -r 's/^([^\s]{9}log_driver[^\s]{2} =) [^\s]+$/\1 "syslog";/g' \
        /app/config/defaults.inc.php \
    \
<? if ($isApacheImage) { ?>
 # Fix Roundcube .htaccess for PHP7
 && sed -i -r 's/^(<IfModule mod)_php5/\1_php7/g' \
        /app/.htaccess \
 && sed -i -r 's/^(php_flag[ ]+(register_globals|magic_quotes|suhosin))/#\1/g' \
        /app/.htaccess \
    \
<? } ?>
 # Setup serve directories
 && cd /app \
 && ln -sn ./public_html /app/html \
 && rm -rf /var/www \
 && ln -s /app /var/www \
    \
 # Set correct owner
 && chown -R www-data:www-data /app /var/www \
    \
 # Cleanup stuff
 && (find /app/ -name .travis.yml -type f -prune | \
        while read d; do rm -rf $d; done) \
 && (find /app/ -name .gitignore -type f -prune | \
        while read d; do rm -rf $d; done) \
 && (find /app/ -name .git -type d -prune | \
        while read d; do rm -rf $d; done) \
<? if ($isApacheImage) { ?>
 && apt-get purge -y --auto-remove \
                  -o APT::AutoRemove::RecommendsImportant=false \
            $toolDeps \
 && rm -rf /var/lib/apt/lists/* \
<? } else { ?>
 && apk del .tool-deps \
 && rm -rf /var/cache/apk/* \
<? } ?>
           /app/temp/* \
           /root/.composer \
           /tmp/*


# Install configurations
COPY rootfs /

# Fix executable rights
RUN chmod +x /etc/services.d/*/run \
             /docker-entrypoint.sh \
<? if ($isApacheImage) { ?>
    \
 # Fix container entrypoint shell usage
 && sed -i -e 's,^#!/bin/sh,#!/bin/bash,' /docker-entrypoint.sh \
<? } ?>
    \
 # Prepare directory for SQLite database
 && mkdir -p /var/db \
 && chown -R www-data:www-data /var/db

ENV PHP_OPCACHE_REVALIDATION=0 \
    SHARE_APP=0


WORKDIR /var/www

ENTRYPOINT ["/init", "/docker-entrypoint.sh"]

<? if ($isApacheImage) { ?>
CMD ["apache2-foreground"]
<? } else { ?>
CMD ["php-fpm"]
<? } ?>

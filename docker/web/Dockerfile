# Stage 1: install PHP libraries (PHPMailer) with Composer.
# Versions are pinned in composer.lock. Only the finished vendor/ folder is copied below.
FROM composer:2.11 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --classmap-authoritative

# Stage 2: the website.
FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo_mysql

# PHP libraries live in /var/www/vendor, outside the web root (/var/www/html = docs/).
COPY --from=vendor /app/vendor /var/www/vendor

# Hardened PHP settings
RUN { \
      echo 'upload_max_filesize = 6M'; \
      echo 'post_max_size = 8M'; \
      echo 'display_errors = Off'; \
      echo 'log_errors = On'; \
      echo 'session.cookie_httponly = 1'; \
      echo 'session.cookie_samesite = Lax'; \
      echo 'session.use_strict_mode = 1'; \
    } > /usr/local/etc/php/conf.d/dissent.ini

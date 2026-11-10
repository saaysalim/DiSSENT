FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo_mysql

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

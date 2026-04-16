FROM wordpress:6.9.4-php8.3-apache
RUN pecl install xdebug \
  && docker-php-ext-enable xdebug
USER www-data

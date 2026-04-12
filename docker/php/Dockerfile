FROM php:8.4-fpm-alpine

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache bash git unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" mysqli pdo_mysql opcache \
    && pecl install redis pcov \
    && docker-php-ext-enable redis pcov \
    && git config --system --add safe.directory /var/www/html \
    && apk del .build-deps

COPY docker/php/php.common.ini /usr/local/etc/php/myxa/php.common.ini
COPY docker/php/php.dev.ini /usr/local/etc/php/myxa/php.dev.ini
COPY docker/php/php.prod.ini /usr/local/etc/php/myxa/php.prod.ini
COPY docker/php/docker-entrypoint.sh /usr/local/bin/myxa-php-entrypoint

RUN chmod +x /usr/local/bin/myxa-php-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["myxa-php-entrypoint"]
CMD ["php-fpm"]

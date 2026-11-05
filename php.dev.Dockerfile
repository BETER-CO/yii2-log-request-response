FROM php:8.5-fpm-alpine AS php-runtime

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN set -eux; \
    rm /usr/local/etc/php-fpm.conf \
       /usr/local/etc/php-fpm.conf.default \
    ; \
    rm -rf /usr/local/etc/php-fpm.d

RUN set -eux; \
    install-php-extensions \
      xdebug-^3.5 \
      intl \
      zip \
    ;

COPY ./deploy/data/php/root/usr/local/etc/ /usr/local/etc/

# Use PHP 8.5 defaults and expose deprecations during development.
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Build with --target php-runtime to check the package without the example app.
FROM php-runtime AS development

COPY ./deploy/data/php/root/var/www/html/ /var/www/html/
COPY ./composer.json /package-src/composer.json
COPY ./src/ /package-src/src/

RUN set -eux; \
    composer install --no-interaction --prefer-dist; \
    mkdir -p runtime; \
    chmod -R a+rwX runtime

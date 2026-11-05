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

RUN set -eux; \
    composer -n create-project --prefer-dist yiisoft/yii2-app-basic .

COPY ./deploy/data/php/root/var/www/html/ /var/www/html/

RUN set -eux; \
    export \
      PACKAGE_VERSION="1.0.0" \
      PACKAGE_ARCH_NAME="yii2-log-request-response" \
    ; \
    export \
      PACKAGE_URL="https://github.com/BETER-CO/${PACKAGE_ARCH_NAME}/archive/refs/tags/${PACKAGE_VERSION}.tar.gz" \
      PACKAGE_ARCH_DIR="${PACKAGE_ARCH_NAME}-${PACKAGE_VERSION}" \
    ; \
    curl -fsSL -o "/${PACKAGE_ARCH_NAME}.tar.gz" "$PACKAGE_URL"; \
    tar -xzf "/${PACKAGE_ARCH_NAME}.tar.gz" -C /tmp/; \
    rm "/${PACKAGE_ARCH_NAME}.tar.gz"; \
    mv /tmp/${PACKAGE_ARCH_DIR} /package-src; \
    composer update

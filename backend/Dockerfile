FROM php:8.2-fpm

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'expose_php = Off\n' > "$PHP_INI_DIR/conf.d/security.ini" \
    && groupadd --gid 1000 appgroup \
    && useradd --uid 1000 --gid appgroup --create-home appuser

COPY --chown=appuser:appgroup api.php ./api.php

USER appuser

EXPOSE 9000

FROM php:8.3-apache-bookworm@sha256:a14446c32a3c8aeb9b4dac7fb73b03dfd8006e670d9b43ea7947e4030d3cbae6 AS build

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libxml2-dev \
    && docker-php-ext-install mysqli curl dom

FROM debian:bookworm-slim@sha256:e67eeead736ff7bbe258ef088bff683635c561b1fcfefad0806c1df4ab49ce0e AS runtime

ENV PHP_INI_DIR=/usr/local/etc/php \
    APACHE_CONFDIR=/etc/apache2 \
    APACHE_ENVVARS=/etc/apache2/envvars

RUN apt-get update \
    && apt-get install -y --no-install-recommends apache2 apache2-utils ca-certificates \
       libargon2-1 libcurl4 libonig5 libreadline8 libsqlite3-0 libssl3 libxml2 libsodium23 zlib1g \
    && rm -rf /var/lib/apt/lists/* /etc/apache2

COPY --from=build /usr/local/bin/php /usr/local/bin/php
COPY --from=build /usr/local/bin/docker-php-entrypoint /usr/local/bin/apache2-foreground /usr/local/bin/
COPY --from=build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=build /usr/local/etc/php/ /usr/local/etc/php/
COPY --from=build /usr/lib/apache2/modules/libphp.so /usr/lib/apache2/modules/libphp.so
COPY --from=build /etc/apache2/ /etc/apache2/

RUN mkdir -p /var/run/apache2 /var/lock/apache2 \
    && ln -sf /dev/stderr /var/log/apache2/error.log \
    && ln -sf /dev/stdout /var/log/apache2/access.log \
    && ln -sf /dev/stdout /var/log/apache2/other_vhosts_access.log \
    && a2enmod rewrite headers \
    && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\nHeader always unset X-Powered-By\n' > /etc/apache2/conf-available/hardening.conf \
    && printf '<Directory /var/www/html>\nOptions -Indexes\nAllowOverride None\nRequire all granted\n</Directory>\n<FilesMatch "(?i)\\.php$">\nRequire all denied\n</FilesMatch>\n' >> /etc/apache2/conf-available/hardening.conf \
    && printf 'expose_php=Off\n' > /usr/local/etc/php/conf.d/hide-php.ini \
    && a2enconf hardening

WORKDIR /opt/dma/backend

RUN find /var/www/html -mindepth 1 -delete

COPY backend/ /opt/dma/backend/
COPY public/ /var/www/html/

RUN find /var/www/html -type d -exec chmod 0755 {} + \
    && find /var/www/html -type f -exec chmod 0644 {} + \
    && find /opt/dma/backend -type d -exec chmod 0755 {} + \
    && find /opt/dma/backend -type f -exec chmod 0644 {} +

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD php /opt/dma/backend/health.php

EXPOSE 80
ENTRYPOINT ["docker-php-entrypoint"]
CMD ["apache2-foreground"]

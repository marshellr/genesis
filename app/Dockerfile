FROM php:8.3-apache-bookworm@sha256:a14446c32a3c8aeb9b4dac7fb73b03dfd8006e670d9b43ea7947e4030d3cbae6

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends apache2 apache2-bin apache2-data apache2-utils linux-libc-dev \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/* \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/genesis.conf \
    && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\nHeader always unset X-Powered-By\n' > /etc/apache2/conf-available/hardening.conf \
    && printf 'expose_php=Off\n' > /usr/local/etc/php/conf.d/hide-php.ini \
    && a2enconf hardening \
    && a2enconf genesis

WORKDIR /var/www/html

COPY . /var/www/html

RUN find /var/www/html -type d -exec chmod 0755 {} + \
    && find /var/www/html -type f -exec chmod 0644 {} +

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/health") === false ? 1 : 0);'

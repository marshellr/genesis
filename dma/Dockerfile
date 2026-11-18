FROM php:8.3-apache-bookworm@sha256:a14446c32a3c8aeb9b4dac7fb73b03dfd8006e670d9b43ea7947e4030d3cbae6

RUN apt-get update \
    && apt-get install -y --no-install-recommends apache2 apache2-bin apache2-data apache2-utils linux-libc-dev libcurl4-openssl-dev libxml2-dev \
    && docker-php-ext-install mysqli curl dom \
    && a2enmod rewrite headers \
    && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\nHeader always unset X-Powered-By\n' > /etc/apache2/conf-available/hardening.conf \
    && printf 'expose_php=Off\n' > /usr/local/etc/php/conf.d/hide-php.ini \
    && a2enconf hardening \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html

RUN find /var/www/html -type d -exec chmod 0755 {} + \
    && find /var/www/html -type f -exec chmod 0644 {} +

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/health.php") === false ? 1 : 0);'

FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libxml2-dev \
    && docker-php-ext-install mysqli curl dom \
    && a2enmod rewrite headers \
    && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\nHeader always unset X-Powered-By\n' > /etc/apache2/conf-available/hardening.conf \
    && printf 'expose_php=Off\n' > /usr/local/etc/php/conf.d/hide-php.ini \
    && a2enconf hardening \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/health.php") === false ? 1 : 0);'

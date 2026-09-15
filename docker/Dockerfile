# Evolution CMS 3.5.x + eMCP from the working copy, for testing the MCP API end to end.
#
#   cd docker && docker compose up --build
#
# Site:     http://localhost:8080/            manager: http://localhost:8080/manager/ (admin / 123456)
# MCP:      http://localhost:8080/mcp/content  token: printed at first start, also in /var/www/html/core/storage/emcp-token.txt
FROM php:8.3-apache

ARG EVO_BRANCH=3.5.x

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl libzip-dev libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev libicu-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd zip intl pdo_mysql opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    APACHE_DOCUMENT_ROOT=/var/www/html

RUN sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    # ServerName silences the FQDN warning; SetEnvIf hands Bearer tokens to PHP under mod_php
    && printf 'ServerName localhost\nSetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1\n' > /etc/apache2/conf-enabled/evo-mcp.conf \
    && printf 'memory_limit=512M\nupload_max_filesize=64M\npost_max_size=64M\n' > /usr/local/etc/php/conf.d/evo.ini

WORKDIR /var/www/html

# Core checkout + core dependencies are baked into the image; site install happens at first start.
RUN git clone --depth 1 --branch "${EVO_BRANCH}" https://github.com/evolution-cms/evolution.git /tmp/evo \
    && cp -a /tmp/evo/. /var/www/html/ && rm -rf /tmp/evo \
    && cp ht.access .htaccess \
    && cd core && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

COPY entrypoint.sh /usr/local/bin/evo-entrypoint.sh
RUN chmod +x /usr/local/bin/evo-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/evo-entrypoint.sh"]
CMD ["apache2-foreground"]

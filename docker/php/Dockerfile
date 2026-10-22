FROM php:8.2.35-apache

RUN apt-get update \
    && apt-get install -y libpq-dev libpng-dev libwebp-dev ca-certificates \
    && docker-php-ext-configure gd --with-webp \
    && docker-php-ext-install pdo pdo_pgsql gd opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Set document root
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Performance: opcache settings, sprite cache rewrites, long-lived static caching
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-performance.ini
COPY docker/php/apache-performance.conf /etc/apache2/conf-enabled/zz-performance.conf

# COPY YOUR SOURCE CODE INTO THE CONTAINER
COPY src/ /var/www/html/

# Resized sprites are written here at runtime
RUN mkdir -p /var/www/html/public/sprites \
    && chown www-data:www-data /var/www/html/public/sprites

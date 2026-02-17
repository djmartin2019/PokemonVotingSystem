FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y libpq-dev ca-certificates \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Set document root
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# COPY YOUR SOURCE CODE INTO THE CONTAINER
COPY src/ /var/www/html/

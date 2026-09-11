FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev libonig-dev libxml2-dev unzip \
    && docker-php-ext-install pdo_mysql mbstring zip simplexml \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

COPY . /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/activos-entrypoint

RUN chmod +x /usr/local/bin/activos-entrypoint \
    && mkdir -p /var/www/html/storage/facturas /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/storage

ENTRYPOINT ["activos-entrypoint"]
CMD ["apache2-foreground"]

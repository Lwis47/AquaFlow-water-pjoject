FROM php:8.3-apache

RUN docker-php-ext-install pdo_sqlite \
    && a2enmod headers

WORKDIR /var/www/html
COPY . .
RUN mkdir -p backend/storage \
    && chown -R www-data:www-data backend/storage

EXPOSE 80

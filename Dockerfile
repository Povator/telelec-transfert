FROM php:8.2-fpm

RUN docker-php-ext-install pdo pdo_mysql

RUN a2enmod rewrite
RUN service apache2 restart

WORKDIR /var/www/html




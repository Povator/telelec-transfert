FROM php:8.2-apache

# Installation des dépendances et extensions PHP
RUN apt-get update && apt-get install -y \
    cron \
    && docker-php-ext-install pdo pdo_mysql

# Copier la configuration personnalisée
COPY php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Créer le fichier de log pour cron
RUN touch /var/log/cron.log && chmod 666 /var/log/cron.log

# Configuration de cron
COPY docker-cron /etc/cron.d/docker-cron
RUN chmod 0644 /etc/cron.d/docker-cron && \
    crontab /etc/cron.d/docker-cron

WORKDIR /var/www/html

RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod -R 755 /var/www/html/uploads


RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN a2enmod rewrite


# Script de démarrage
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
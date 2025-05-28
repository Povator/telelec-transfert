FROM php:8.2-fpm

# Installation des dépendances et extensions PHP
RUN apt-get update && apt-get install -y \
    cron \
    && docker-php-ext-install pdo pdo_mysql

# Copier la configuration personnalisée
COPY php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Créer le fichier de log pour cron
RUN touch /var/log/cron.log && chmod 666 /var/log/cron.log

# Configuration du répertoire de travail
WORKDIR /var/www/html

RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod -R 755 /var/www/html/uploads

# Script de démarrage (si tu veux démarrer cron + php-fpm)
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
FROM php:8.1-apache

# Installa le dipendenze necessarie
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libcurl4-openssl-dev \
    cron \
    procps \
    && docker-php-ext-install \
    pdo_mysql \
    curl \
    dom

# Crea un link simbolico per PHP CLI (che è già incluso nell'immagine php:8.1-apache)
RUN ln -sf /usr/local/bin/php /usr/bin/php

# Abilita il modulo rewrite di Apache
RUN a2enmod rewrite

# Copia i file del progetto nella directory di lavoro
COPY . /var/www/html/

# Imposta i permessi corretti
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configura PHP per produzione
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/memory-limit.ini \
    && echo "max_execution_time = 300" > /usr/local/etc/php/conf.d/max-execution-time.ini \
    && echo "display_errors = Off" > /usr/local/etc/php/conf.d/error-reporting.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/error-reporting.ini \
    && echo "error_log = /var/log/php_errors.log" >> /usr/local/etc/php/conf.d/error-reporting.ini

# Configura Apache per gestire gli errori
RUN echo "php_flag display_errors off" >> /etc/apache2/conf-available/php.conf \
    && echo "php_flag log_errors on" >> /etc/apache2/conf-available/php.conf \
    && a2enconf php

# Espone la porta 80
EXPOSE 80

# Comando di avvio
CMD ["apache2-foreground"]

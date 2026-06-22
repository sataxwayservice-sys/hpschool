FROM php:8.1-apache

# Install extensions and utilities
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev zip unzip git \
    && docker-php-ext-install mysqli pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Copy project files
COPY . /var/www/html/

# Ensure proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]

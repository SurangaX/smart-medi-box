FROM php:8.2-apache

# Enable mod_rewrite for routing
RUN a2enmod rewrite

# Install PostgreSQL extension
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Set working directory
WORKDIR /var/www/html

# Copy API files
COPY . .

# Set Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Configure Apache
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Expose port
EXPOSE 8080

# Set Apache to listen on 8080 for Render
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf

# Start Apache
CMD ["apache2-foreground"]

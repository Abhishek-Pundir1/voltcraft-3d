FROM php:8.2-apache

# Install required PHP extensions for MySQL and GD (if used for image processing)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Update Apache configuration to use the PORT environment variable
# Render provides the PORT environment variable dynamically
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Copy application files to the container
COPY . /var/www/html/

# Set appropriate permissions
RUN chown -R www-data:www-data /var/www/html/
RUN chmod -R 755 /var/www/html/
RUN chmod -R 777 /var/www/html/assets/uploads

# Expose port (it will be injected by Render, but default to 80 for local testing)
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]

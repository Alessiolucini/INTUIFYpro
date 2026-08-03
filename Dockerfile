FROM php:8.2-apache

# Install system dependencies for PHP extensions and DomPDF
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (curl and mbstring are already in php:8.2-apache)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip bcmath

# Enable Apache modules needed by .htaccess
RUN a2enmod rewrite headers deflate expires

# Set the document root to /var/www/html
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Increase PHP execution timeout for AI generation (default 30s is too short)
RUN echo "max_execution_time = 180" > /usr/local/etc/php/conf.d/intuify.ini \
 && echo "max_input_time = 120" >> /usr/local/etc/php/conf.d/intuify.ini \
 && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/intuify.ini

# Configure Apache to allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files first for better layer caching
COPY composer.json /var/www/html/
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

# Copy all project files
COPY . /var/www/html/

# Run composer install again to ensure deps are present
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader --no-interaction

# Set proper ownership
RUN chown -R www-data:www-data /var/www/html

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 80
EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]

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
RUN a2enmod rewrite headers deflate expires remoteip

# Real client IP behind Traefik: trust X-Forwarded-For only from private (Docker) networks.
# Without this REMOTE_ADDR is the proxy IP for everyone (rate limits, audit log, reCAPTCHA).
RUN printf '%s\n' \
      'RemoteIPHeader X-Forwarded-For' \
      'RemoteIPTrustedProxy 10.0.0.0/8 172.16.0.0/12 192.168.0.0/16' \
      > /etc/apache2/conf-available/remoteip.conf \
 && a2enconf remoteip

# Set the document root to /var/www/html
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Increase PHP execution timeout for AI generation (default 30s is too short)
RUN echo "max_execution_time = 180" > /usr/local/etc/php/conf.d/intuify.ini \
 && echo "max_input_time = 120" >> /usr/local/etc/php/conf.d/intuify.ini \
 && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/intuify.ini \
 && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/intuify.ini \
 && echo "log_errors = On" >> /usr/local/etc/php/conf.d/intuify.ini \
 && echo "expose_php = Off" >> /usr/local/etc/php/conf.d/intuify.ini

# Configure Apache to allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files first for better layer caching
COPY composer.json /var/www/html/
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

# Copy all project files (.dockerignore keeps .git, notes and monitoring configs out)
COPY . /var/www/html/

# Build/deploy files must never be downloadable from the web root
RUN rm -f /var/www/html/Dockerfile \
          /var/www/html/docker-entrypoint.sh \
          /var/www/html/admin/migration.sql

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

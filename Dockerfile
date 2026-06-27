FROM php:8.2-apache

# Enable Apache modules needed by .htaccess
RUN a2enmod rewrite headers deflate expires

# Set the document root to /var/www/html
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Configure Apache to allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy all project files
COPY . /var/www/html/

# Set proper ownership
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

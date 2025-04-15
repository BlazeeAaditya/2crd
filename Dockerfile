FROM php:5.6-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Install the mysql extension (older PHP versions like 5.5 should include it by default)
RUN apt-get update && apt-get install -y \
    libmysqlclient-dev && \
    docker-php-ext-install mysql

# Set PHP configurations for large uploads
RUN echo "upload_max_filesize = 3G" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 3G" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit = 3G" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time = 600" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_input_time = 600" >> /usr/local/etc/php/conf.d/uploads.ini

# Disable deprecation warnings for mysql extension usage
RUN echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT" >> /usr/local/etc/php/conf.d/custom.ini

# Add Apache config for large requests
RUN echo "LimitRequestBody 12884901888" >> /etc/apache2/apache2.conf

# Copy app
COPY app/ /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Allow .htaccess overrides
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Restart apache to apply changes
RUN service apache2 restart

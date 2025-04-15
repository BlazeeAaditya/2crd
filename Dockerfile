FROM php:5.6-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Install legacy extensions including mysql
RUN docker-php-ext-install mysql mysqli pdo pdo_mysql

# Install php5.6-mysql using apt-get
RUN apt-get update && apt-get install -y php5.6-mysql

# Set PHP configurations for large uploads
RUN echo "upload_max_filesize = 3G" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 3G" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit = 3G" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time = 600" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_input_time = 600" >> /usr/local/etc/php/conf.d/uploads.ini

# Add Apache config for large requests
RUN echo "LimitRequestBody 12884901888" >> /etc/apache2/apache2.conf

# Set display_errors to Off
RUN echo "display_errors = Off" >> /usr/local/etc/php/conf.d/display_errors.ini

# Enable error logging
RUN echo "log_errors = On" >> /usr/local/etc/php/conf.d/log_errors.ini && \
    echo "error_log = /var/log/php_errors.log" >> /usr/local/etc/php/conf.d/log_errors.ini

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

FROM php:5.6-apache

# Enable mod_rewrite for Apache
RUN a2enmod rewrite

# Install necessary PHP extensions including legacy mysql
RUN docker-php-ext-install mysql mysqli pdo pdo_mysql

# Set PHP config for large uploads
RUN echo "upload_max_filesize = 3G" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 3G" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit = 3G" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time = 600" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_input_time = 600" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_NOTICE" >> /usr/local/etc/php/conf.d/error_level.ini

# Increase Apache body size limit
RUN echo "LimitRequestBody 12884901888" >> /etc/apache2/apache2.conf

# Allow .htaccess overrides
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Copy app source
COPY app/ /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Install and enable mysql extension (deprecated but required)
RUN echo "extension=mysql.so" > /usr/local/etc/php/conf.d/mysql.ini

# Restart Apache (not necessary in Docker but can stay for clarity)
RUN service apache2 restart

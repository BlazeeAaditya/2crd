FROM php:5.6-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Install legacy extensions including mysql
RUN docker-php-ext-install mysql mysqli pdo pdo_mysql

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
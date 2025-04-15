FROM crazymax/php:5.3

# Install Apache and required packages
RUN apt-get update && \
    apt-get install -y apache2 libapache2-mod-php5 curl unzip && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable mod_rewrite
RUN a2enmod rewrite

# Set PHP config for large uploads
RUN echo "upload_max_filesize = 3G" >> /etc/php5/apache2/php.ini && \
    echo "post_max_size = 3G" >> /etc/php5/apache2/php.ini && \
    echo "memory_limit = 3G" >> /etc/php5/apache2/php.ini && \
    echo "max_execution_time = 600" >> /etc/php5/apache2/php.ini && \
    echo "max_input_time = 600" >> /etc/php5/apache2/php.ini && \
    echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_NOTICE" >> /etc/php5/apache2/php.ini

# Increase Apache body size limit
RUN echo "LimitRequestBody 12884901888" >> /etc/apache2/apache2.conf

# Allow .htaccess overrides
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Set up document root and copy your app
COPY app/ /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Install legacy mysql extension
RUN echo "extension=mysql.so" > /etc/php5/apache2/conf.d/mysql.ini

# Expose HTTP port
EXPOSE 80

# Start Apache in foreground
CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]

# Use an official Debian image as a base
FROM debian:stretch

# Install dependencies
RUN apt-get update && apt-get install -y \
    wget \
    curl \
    build-essential \
    libxml2-dev \
    libcurl4-openssl-dev \
    libjpeg-dev \
    libpng-dev \
    libmcrypt-dev \
    libmysqlclient-dev \
    libicu-dev \
    apache2 \
    && apt-get clean

# Download PHP 5.6.14 source
RUN wget https://www.php.net/distributions/php-5.6.14.tar.bz2

# Extract PHP source
RUN tar -xjf php-5.6.14.tar.bz2

# Build and install PHP 5.6.14 from source
WORKDIR php-5.6.14
RUN ./configure --with-apxs2=/usr/bin/apxs --with-mysqli --with-curl --enable-soap --with-zlib --with-mhash --enable-bcmath --with-mcrypt \
    && make \
    && make install

# Configure Apache
RUN a2enmod rewrite

# Set PHP configurations for large uploads
RUN echo "upload_max_filesize = 3G" > /etc/php/5.6/apache2/php.ini \
    && echo "post_max_size = 3G" >> /etc/php/5.6/apache2/php.ini \
    && echo "memory_limit = 3G" >> /etc/php/5.6/apache2/php.ini \
    && echo "max_execution_time = 600" >> /etc/php/5.6/apache2/php.ini \
    && echo "max_input_time = 600" >> /etc/php/5.6/apache2/php.ini

# Add Apache config for large requests
RUN echo "LimitRequestBody 12884901888" >> /etc/apache2/apache2.conf

# Copy app to Apache server
COPY app/ /var/www/html/

# Set correct permissions for the app
RUN chown -R www-data:www-data /var/www/html

# Allow .htaccess overrides
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Expose Apache HTTP port
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2ctl", "-D", "FOREGROUND"]

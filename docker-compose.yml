# Use a base image with Apache
FROM debian:stretch-slim

# Install PHP 5.3 and Apache
RUN apt-get update && apt-get install -y \
    apache2 \
    php5 \
    libapache2-mod-php5 \
    php5-mysql \
    php5-cli \
    php5-curl \
    php5-mcrypt \
    php5-mbstring \
    php5-json \
    php5-intl \
    php5-xmlrpc \
    php5-soap \
    php5-zip \
    && apt-get clean

# Enable mod_rewrite for Apache
RUN a2enmod rewrite

# Set PHP config for large uploads
RUN echo "upload_max_filesize = 3G" > /etc/php5/apache2/php.ini && \
    echo "post_max_size = 3G" >> /etc/php5/apache2/php.ini && \
    echo "memory_limit = 3G" >> /etc/php5/apache2/php.ini && \
    echo "max_execution_time = 600" >> /etc/php5/apache2/php.ini && \
    echo "max_input_time = 600" >> /etc/php5/apache2/php.ini && \
    echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_NOTICE" >> /etc/php5/apache2/php.ini

# Copy your app to the container
COPY ./app/ /var/www/html/

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apachectl", "-D", "FOREGROUND"]

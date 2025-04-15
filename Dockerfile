# Use an appropriate base image
FROM php:5.6-apache

# Set environment variables for curl and library paths
ENV CFLAGS="-I/usr/include/curl"
ENV LDFLAGS="-L/usr/lib/x86_64-linux-gnu"

# Install system dependencies
RUN apt-get update && apt-get install -y \
    wget \
    curl \
    build-essential \
    libxml2-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libjpeg-dev \
    libpng-dev \
    libmcrypt-dev \
    libmariadb-dev-compat \
    libicu-dev \
    apache2-dev \
    apache2 \
    && apt-get clean

# Install libcurl from source if needed (optional if the above doesn't work)
RUN cd /tmp && \
    wget https://curl.se/download/curl-7.79.1.tar.gz && \
    tar -xvzf curl-7.79.1.tar.gz && \
    cd curl-7.79.1 && \
    ./configure && \
    make && \
    make install

# Ensure the necessary directories and libraries are available
RUN ln -s /usr/include/curl /usr/local/include/curl

# Set the working directory
WORKDIR /var/www/html

# Install required PHP extensions
RUN docker-php-ext-install \
    mysqli \
    soap \
    bcmath \
    mcrypt \
    && docker-php-ext-enable soap

# Configure Apache
RUN a2enmod rewrite

# Enable any necessary PHP settings (if needed)
COPY php.ini /usr/local/etc/php/

# Expose the necessary port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]

FROM php:8.1-apache

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite

RUN apt-get update && \
    apt-get install -y wget p7zip-full ffmpeg && \
    mkdir -p /app/ffmpeg && \
    wget https://www.gyan.dev/ffmpeg/builds/ffmpeg-git-essentials.7z -O /app/ffmpeg/ffmpeg.7z && \
    7z x /app/ffmpeg/ffmpeg.7z -o/app/ffmpeg


# Copy the Symfony application to the container
COPY . /var/www/html

RUN mkdir -p var/cache var/log && \
    chown -R www-data:www-data var/cache var/log && \
    chmod -R a+rwx var/cache var/log && \
    chown -R www-data:www-data public/logs

RUN chown -R www-data:www-data /var/www/html/public && \
    chmod -R a+w /var/www/html/public


# Set environment variable for Apache listen port
ENV APACHE_LISTEN_PORT=8080

# Update Apache configuration to use new listen port
RUN sed -i -e "s/80/${APACHE_LISTEN_PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install dependencies using composer
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress --optimize-autoloader

# Set permissions for the cache and logs directories
RUN chown -R www-data:www-data var/cache var/log

# Expose the new listen port
EXPOSE ${APACHE_LISTEN_PORT}

# Start Apache
CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
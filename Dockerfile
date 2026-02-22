FROM php:8.3-apache

# Install PostgreSQL client libs and PHP pgsql extensions
RUN apt-get update && apt-get install -y \
        libpq-dev \
        postgresql-client \
        unzip \
        git \
        pdftk-java \
        ghostscript \
        imagemagick \
        msmtp \
        msmtp-mta \
    && docker-php-ext-install pgsql pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# msmtp config is generated at container startup from env vars (see docker-entrypoint.sh)
RUN echo 'sendmail_path = "/usr/bin/msmtp -t --read-envelope-from"' \
    > /usr/local/etc/php/conf.d/mail.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Suppress PHP warnings/notices from being rendered in the browser
RUN echo "display_errors = Off" > /usr/local/etc/php/conf.d/suppress-errors.ini \
 && printf "upload_max_filesize = 512M\npost_max_size = 512M\nmemory_limit = 512M\nmax_execution_time = 300\noutput_buffering = 4096\n" > /usr/local/etc/php/conf.d/uploads.ini \
 && printf "log_errors = On\nerror_log = /dev/stderr\n" > /usr/local/etc/php/conf.d/logging.ini

# Suppress Apache ServerName warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Allow symlinks and .htaccess in the webroot
RUN sed -i 's|<Directory /var/www/>|<Directory /var/www/>\n\tOptions FollowSymLinks|' /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

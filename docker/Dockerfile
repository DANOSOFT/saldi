FROM php:8.2-apache

# Install system dependencies
RUN set -eux; \
    apt-get update; \
    apt-get install -y \
        postgresql-client \
        ghostscript \
        imagemagick \
        barcode \
        mpack \
        weasyprint \
        locales \
        unzip \
        libpq-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev; \
    \
    # Note: pdftk is often removed from newer Debian releases. If essential, an alternative might be needed. \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        pgsql \
        pdo_pgsql \
        gd \
        mbstring \
        zip \
        bcmath \
        opcache; \
    \
    # Remove build-only deeps to keep the runtime image smaller \
    apt-get purge -y \
        $PHPIZE_DEPS \
        libpq-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev; \
    rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/*

# ImageMagick Policy relaxation (Security risk, but requested by user)
RUN if [ -f /etc/ImageMagick-6/policy.xml ]; then \
      sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/g' /etc/ImageMagick-6/policy.xml && \
      sed -i 's/rights="none" pattern="PS"/rights="read|write" pattern="PS"/g' /etc/ImageMagick-6/policy.xml && \
      sed -i 's/rights="none" pattern="EPS"/rights="read|write" pattern="EPS"/g' /etc/ImageMagick-6/policy.xml; \
    fi

# Apache Configuration
RUN a2enmod rewrite ssl headers

# Set default locale to Danish as implied by context
RUN echo "da_DK.UTF-8 UTF-8" > /etc/locale.gen && locale-gen
ENV LANG=da_DK.UTF-8  
ENV LANGUAGE=da_DK:da  
ENV LC_ALL=da_DK.UTF-8

# Custom PHP Config
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
# Custom Vhost
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
# Entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Ensure required writable directories exist (in case volume mount doesn't have them)
RUN mkdir -p /var/www/html/temp \
    /var/www/html/includes/temp \
    /var/www/html/bordplaner/temp \
    /var/www/html/logolib

# Note: chown is handled by entrypoint.sh at runtime since src/ is volume-mounted

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]

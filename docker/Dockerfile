FROM php:8.2-apache

RUN set -eux; \
    export DEBIAN_FRONTEND=noninteractive; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
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
        libjpeg-dev \dependencies
        libfreetype6-dev \
        libzip-dev \
        libonig-dev; \
    rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/*

RUN if [ -f /etc/ImageMagick-6/policy.xml ]; then \
      sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/g' /etc/ImageMagick-6/policy.xml && \
      sed -i 's/rights="none" pattern="PS"/rights="read|write" pattern="PS"/g' /etc/ImageMagick-6/policy.xml && \
      sed -i 's/rights="none" pattern="EPS"/rights="read|write" pattern="EPS"/g' /etc/ImageMagick-6/policy.xml; \
    fi

RUN a2enmod rewrite ssl headers

RUN echo "da_DK.UTF-8 UTF-8" > /etc/locale.gen && locale-gen
ENV LANG=da_DK.UTF-8  
ENV LANGUAGE=da_DK:da  
ENV LC_ALL=da_DK.UTF-8

COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY src/ /var/www/html/saldi/

RUN chown -R www-data:www-data /var/www/html/saldi

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]

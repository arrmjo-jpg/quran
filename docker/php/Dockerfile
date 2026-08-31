FROM php:8.4-fpm-alpine

# Install system dependencies including FFmpeg and FFprobe
RUN apk add --no-cache \
    ffmpeg \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    bash \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        zip \
        opcache

# Install Redis PHP Extension via PECL / source
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Platform PHP settings. The image previously loaded no php.ini at all, so
# every value was PHP's compiled default -- including a memory_limit the test
# suite outgrew. See the file for the measurement.
COPY php.ini /usr/local/etc/php/conf.d/zz-platform.ini

# Copy Composer binary from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

EXPOSE 9000

CMD ["php-fpm"]

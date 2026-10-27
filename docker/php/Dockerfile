FROM php:8.3-fpm

# Dépendances système nécessaires aux extensions PHP (images, intl, zip)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        intl \
        zip \
        gd \
        exif \
        opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Symfony CLI
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash \
    && apt-get install -y --no-install-recommends symfony-cli \
    && rm -rf /var/lib/apt/lists/*

# Configuration PHP du projet (limites d'upload pour photos et vidéos)
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-sarah.ini

# Utilisateur aux mêmes UID/GID que l'hôte pour éviter les fichiers appartenant à root
ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} sarah \
    && useradd -u ${UID} -g ${GID} -m -s /bin/bash sarah \
    && sed -i 's/^user = www-data/user = sarah/; s/^group = www-data/group = sarah/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html
USER sarah

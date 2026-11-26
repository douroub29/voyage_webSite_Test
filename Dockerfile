# syntax=docker/dockerfile:1
# DreamTravel - image PHP 8.2 + Apache
#   docker build --target prod -t dreamtravel .   -> image de production
#   docker build --target dev  -t dreamtravel:dev . -> image de développement (dépendances dev, fixtures)

############################
# Base commune
############################
FROM php:8.2-apache AS base

WORKDIR /var/www/html

# Extensions PHP requises (identiques à la CI)
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl pdo_mysql zip opcache \
    && apt-get update && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Apache : la racine web est public/, toutes les requêtes inconnues vont à index.php
RUN a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    FallbackResource /index.php\n</Directory>\n' \
       > /etc/apache2/conf-available/symfony.conf \
    && a2enconf symfony

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint && chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["apache2-foreground"]

############################
# Développement
############################
FROM base AS dev
ENV APP_ENV=dev
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts
COPY . .
RUN composer run-script auto-scripts \
    && mkdir -p var public/uploads && chown -R www-data:www-data var public/uploads

############################
# Production
############################
FROM base AS prod
ENV APP_ENV=prod APP_DEBUG=0
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && composer run-script auto-scripts \
    && php bin/console asset-map:compile \
    && mkdir -p var public/uploads && chown -R www-data:www-data var public/uploads

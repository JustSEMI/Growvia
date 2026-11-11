# syntax=docker/dockerfile:1

# Growvia backend (Node 1). Multi-target:
#   runtime: PHP-FPM (app & mqtt-consumer)
#   web: nginx (web)
# Database (Node 2) dan MQTT (Node 3) di host lain, diatur via compose.

# Aset frontend (Vite)
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm ci --ignore-scripts \
    && npm run build

# Dependensi PHP produksi
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

# Runtime PHP-FPM
FROM php:8.3-fpm-alpine AS runtime
RUN apk add --no-cache icu-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath intl opcache pcntl

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

# .env dari compose selalu menimpa template ini.
RUN cp .env.example .env \
    && printf "memory_limit=256M\nupload_max_filesize=20M\npost_max_size=20M\nopcache.enable_cli=0\n" > /usr/local/etc/php/conf.d/growvia.ini \
    && chown -R www-data:www-data storage bootstrap/cache .env

USER www-data
EXPOSE 9000
CMD ["php-fpm"]

# nginx: statis + proxy FastCGI ke app:9000
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=runtime /var/www/html/public /var/www/html/public
EXPOSE 80

FROM php:8.4-fpm-bookworm@sha256:b75642e8c89c321039f351466134df951576fb0f7017117b3931e9a19b908d6c AS php-base
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libonig-dev libfcgi-bin unzip \
    && docker-php-ext-install -j$(nproc) pdo_pgsql mbstring opcache \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html

FROM php-base AS vendor
COPY --from=composer:2@sha256:ca11f643d7791ebdd90a34fc20caf11380d434007ae539a9ea75cd9212e13dca /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY app app
COPY database database
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts

FROM node:24-alpine@sha256:116b800c3754b440ee0aa3ed2f20f5bf3d99a554bfd9adeea09ea8017f6e85f5 AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
COPY shared shared
COPY vite.config.ts tsconfig.json ./
RUN npm run build

FROM php-base AS app
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor vendor
COPY --from=assets --chown=www-data:www-data /app/public/build public/build
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
RUN php artisan package:discover --ansi
HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong
CMD ["php-fpm"]

FROM nginx:1.28-alpine@sha256:a8b39bd9cf0f83869a2162827a0caf6137ddf759d50a171451b335cecc87d236 AS nginx
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build
HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1:8080/api/health || exit 1

# API de Robotics (T-20). Contexto de construcción: la carpeta api/ (ver compose.yaml).
FROM php:8.3-cli

# Extensiones que usa la API: pdo_pgsql (PostgreSQL) e intl (orden alfabético en español, NameOrder)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libicu-dev unzip \
    && docker-php-ext-install pdo_pgsql intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api

# Primero solo las dependencias: si el código cambia pero composer.lock no, Docker reutiliza esta capa
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/app/photos storage/framework/cache storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 8000

# Al arrancar: crea o actualiza el esquema, asegura el admin del entorno y atiende peticiones (architecture.md §7)
CMD ["sh", "-c", "php artisan migrate --force && php artisan robotics:ensure-admin && php artisan serve --host=0.0.0.0 --port=8000 --no-reload"]

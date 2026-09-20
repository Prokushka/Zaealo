# Stage 1: Install Composer packages needed by Vite imports.
FROM composer:2 AS composer-dependencies

WORKDIR /var/www

COPY composer.json composer.lock ./

# Scripts need the full Laravel application and are unnecessary for the asset build.
RUN composer install --no-dev --no-scripts --optimize-autoloader --no-interaction --no-progress --prefer-dist --ignore-platform-reqs

COPY . /var/www
RUN php artisan filament:assets

# Stage 2: Build frontend assets.
FROM node:22-bookworm-slim AS builder

WORKDIR /var/www

COPY package.json package-lock.json ./
RUN npm ci

COPY . /var/www
COPY --from=composer-dependencies /var/www/vendor /var/www/vendor
COPY --from=composer-dependencies /var/www/public /var/www/public

RUN npm run build

# Stage 3: Nginx production image
FROM nginx:alpine

# Copy custom Nginx configuration
# -----------------------------------------------------------
# Replace the default Nginx configuration with our custom one
# that is optimized for serving a Laravel application.
# -----------------------------------------------------------
COPY ./docker/production/nginx/nginx.conf /etc/nginx/nginx.conf

# Copy Laravel's public assets from the builder stage
# -----------------------------------------------------------
# We only need the 'public' directory from our Laravel app.
# -----------------------------------------------------------
COPY --from=builder /var/www/public /var/www/public

RUN mkdir -p /var/www/storage/app/public \
    && ln -sfn /var/www/storage/app/public /var/www/public/storage

# Set the working directory to the public folder
WORKDIR /var/www/public

# Expose port 80 and start Nginx
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]

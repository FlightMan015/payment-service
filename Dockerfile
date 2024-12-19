FROM php:8.4-apache AS base

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    zip \
    libsodium-dev

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure zip

RUN docker-php-ext-install -j$(nproc) pdo pdo_mysql pdo_pgsql pcntl intl zip bcmath sodium
RUN pecl install redis && \
    docker-php-ext-enable redis

RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

COPY composer.* .
RUN --mount=type=secret,id=composer_auth,dst=auth.json \
    composer install --no-dev --no-scripts --no-autoloader --no-progress --no-interaction

# The development stage installs the necessary extensions for debugging and code coverage. It also sets the development PHP configuration.
FROM base AS development

RUN pecl install xdebug pcov \
 && docker-php-ext-enable xdebug pcov

RUN curl -L -so /usr/local/bin/infection https://github.com/infection/infection/releases/download/0.28.1/infection.phar
RUN curl -L -so /usr/local/bin/infection.asc https://github.com/infection/infection/releases/download/0.28.1/infection.phar.asc
RUN chmod +x /usr/local/bin/infection

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY . .
RUN --mount=type=secret,id=composer_auth,dst=auth.json \
    composer install --no-progress --no-interaction

# The production stage sets the production PHP configuration and installs the application production dependencies, such as the Datadog PHP tracer.
FROM base

RUN curl -LO https://github.com/DataDog/dd-trace-php/releases/latest/download/datadog-setup.php \
 && php datadog-setup.php --php-bin=all --enable-profiling \
 && rm datadog-setup.php

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY . .
RUN --mount=type=secret,id=composer_auth,dst=auth.json \
    composer install --no-progress --no-interaction --optimize-autoloader

RUN composer dump-autoload --optimize

HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 CMD curl -f http://localhost/ || exit 1

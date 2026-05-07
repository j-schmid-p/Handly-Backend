# Imagen base: PHP 8.2 con Apache incluido
FROM php:8.2-apache

# 1. Instalar dependencias
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean

# Apagamos los motores extra y dejamos solo el que PHP necesita
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork

# 2. Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Activar mod_rewrite (necesario para las rutas de Laravel)
RUN a2enmod rewrite

# 4. Configurar Apache para Railway (usando comillas simples ' ' para proteger la variable PORT)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
RUN sed -i -e 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# 5. Copiar todo el código de tu ordenador al servidor
COPY . /var/www/html/

# 6. Instalar dependencias de Laravel
RUN composer install --no-dev --optimize-autoloader

# 7. Dar los permisos correctos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
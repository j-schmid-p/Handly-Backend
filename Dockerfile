# Imagen base: PHP 8.2 con Apache incluido
FROM php:8.2-apache

# Instalar dependencias del sistema y extensión de PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean

# Activar mod_rewrite (necesario para APIs REST con rutas limpias)
RUN a2enmod rewrite

# Copiar todo el código PHP al servidor web de Apache
COPY . /var/www/html/

# Dar permisos correctos al servidor web
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Apache escucha en el puerto 80
EXPOSE 80
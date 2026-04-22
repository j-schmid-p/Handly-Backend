# Imagen base: PHP 8.2 con Apache incluido
FROM php:8.2-apache

# Instalar dependencias del sistema y extensión de PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean

# Activar mod_rewrite (necesario para APIs REST con rutas limpias)
RUN a2enmod rewrite

# Cambiar el DocumentRoot a la carpeta /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copiar todo el código PHP al servidor web de Apache
COPY . /var/www/html/

# Dar permisos correctos al servidor web (Especialmente a storage y cache)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Apache escucha en el puerto 80
EXPOSE 80
# Imagen base: PHP 8.2 con Apache incluido
FROM php:8.2-apache

# Instalar dependencias del sistema, PostgreSQL, Unzip y Git (para Composer)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean
    
# instalar compose
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Activar mod_rewrite (necesario para APIs REST con rutas limpias)
RUN a2enmod rewrite

# Cambiar el DocumentRoot a la carpeta /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN echo "<VirtualHost *:80>\n\
    DocumentRoot ${APACHE_DOCUMENT_ROOT}\n\
    <Directory ${APACHE_DOCUMENT_ROOT}>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>" > /etc/apache2/sites-available/000-default.conf

# Copiar todo el código PHP al servidor web de Apache
COPY . /var/www/html/

# Dar permisos correctos al servidor web (Especialmente a storage y cache)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Apache escucha en el puerto 80
EXPOSE 80
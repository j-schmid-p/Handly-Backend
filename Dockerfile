# Imagen base: PHP 8.2 con Apache incluido
FROM php:8.2-apache

# 1. Instalar dependencias del sistema y PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean
    
# 2. Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Activar mod_rewrite (necesario para APIs REST)
RUN a2enmod rewrite

# 4. Configurar Apache para que escuche el $PORT dinámico que le dará Railway
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -i "s/Listen 80/Listen \${PORT:-80}/g" /etc/apache2/ports.conf
RUN sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:\${PORT:-80}>/g" /etc/apache2/sites-available/000-default.conf

RUN echo "<VirtualHost *:\${PORT:-80}>\n\
    DocumentRoot ${APACHE_DOCUMENT_ROOT}\n\
    <Directory ${APACHE_DOCUMENT_ROOT}>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>" > /etc/apache2/sites-available/000-default.conf

# 5. Copiar todo el código PHP al servidor web
COPY . /var/www/html/

# 6. INSTALAR DEPENDENCIAS DE LARAVEL
# Usamos --no-dev para no instalar librerías de pruebas y que pese menos
RUN composer install --no-dev --optimize-autoloader

# 7. Dar permisos correctos al servidor web
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Eliminamos la línea EXPOSE 80 porque Railway inyecta el suyo automáticamente.
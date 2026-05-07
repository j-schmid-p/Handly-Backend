# Imagen base: PHP 8.2 con Apache
FROM php:8.2-apache

# 1. Instalar dependencias
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean

# 2. EL MARTILLO: Borramos físicamente los MPM rebeldes y forzamos el prefork
RUN rm -f /etc/apache2/mods-available/mpm_event.conf \
    && rm -f /etc/apache2/mods-available/mpm_worker.conf \
    && rm -f /etc/apache2/mods-enabled/mpm_event.* \
    && rm -f /etc/apache2/mods-enabled/mpm_worker.* \
    && a2enmod mpm_prefork rewrite

# 3. Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. REESCRITURA TOTAL: Configuramos el puerto y la ruta pública desde cero
RUN echo "Listen \${PORT}\n" > /etc/apache2/ports.conf
RUN echo "<VirtualHost *:\${PORT}>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog \${APACHE_LOG_DIR}/error.log\n\
    CustomLog \${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>" > /etc/apache2/sites-available/000-default.conf

# 5. Copiar código
COPY . /var/www/html/

# 6. Instalar dependencias de Laravel
RUN composer install --no-dev --optimize-autoloader

# 7. Permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
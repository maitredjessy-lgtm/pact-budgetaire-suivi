FROM php:8.3-apache

# Extensions : PostgreSQL (base persistante) + zip (import/export Word .docx)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Seul le dossier public/ est expose sur le web (src/, database/, data/ restent prives)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    # Render fournit le port d'ecoute dans $PORT
    && sed -ri -e 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri -e 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
    && a2enmod headers \
    && { echo 'ServerTokens Prod'; echo 'ServerSignature Off'; \
         echo 'Header always set X-Content-Type-Options "nosniff"'; \
         echo 'Header always set X-Frame-Options "SAMEORIGIN"'; \
         echo 'Header always set Referrer-Policy "strict-origin-when-cross-origin"'; } > /etc/apache2/conf-enabled/securite.conf

# Uploads de modeles .docx et reponses IA longues
RUN { echo 'upload_max_filesize=15M'; echo 'post_max_size=16M'; echo 'max_execution_time=150'; \
      echo 'expose_php=Off'; echo 'date.timezone=Africa/Abidjan'; } > /usr/local/etc/php/conf.d/pact.ini

ENV PORT=10000
WORKDIR /var/www/html
COPY . /var/www/html
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html/data

EXPOSE 10000

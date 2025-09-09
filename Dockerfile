# Dockerfile
FROM php:8.2-apache

# Evita prompts no apt
ARG DEBIAN_FRONTEND=noninteractive

# Dependências de sistema para extensões do PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libzip-dev zip \
    libicu-dev \
    # em alguns ambientes ajuda o mbstring
    libonig-dev \
    git ca-certificates pkg-config \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 # compila em série (-j1) pra consumir menos RAM/CPU
 && docker-php-ext-install -j1 gd zip mbstring intl pdo_mysql \
 # garante .ini de enable (algumas imagens já fazem, mas não custa)
 && docker-php-ext-enable gd zip mbstring intl pdo_mysql \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# Apache: AllowOverride All para .htaccess
RUN set -eux; \
  { \
    echo '<Directory /var/www/html/>'; \
    echo '  AllowOverride All'; \
    echo '  Require all granted'; \
    echo '</Directory>'; \
  } > /etc/apache2/conf-available/allow-override.conf \
  && a2enconf allow-override

# Timezone (opcional)
RUN ln -snf /usr/share/zoneinfo/America/Sao_Paulo /etc/localtime \
 && echo America/Sao_Paulo > /etc/timezone

# PHP ini “gentil” p/ uploads
RUN { \
  echo 'file_uploads=On'; \
  echo 'memory_limit=512M'; \
  echo 'post_max_size=32M'; \
  echo 'upload_max_filesize=32M'; \
} > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html
# Se for produção sem bind mount, descomente:
# COPY . /var/www/html

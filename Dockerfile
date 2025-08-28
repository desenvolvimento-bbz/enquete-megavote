# Dockerfile
FROM php:8.2-apache

# Extensões necessárias
RUN docker-php-ext-install pdo pdo_mysql

# Habilita módulos úteis do Apache
RUN a2enmod rewrite headers

# Permite .htaccess, se você usar
RUN set -eux; \
  { \
    echo '<Directory /var/www/html/>'; \
    echo '  AllowOverride All'; \
    echo '  Require all granted'; \
    echo '</Directory>'; \
  } > /etc/apache2/conf-available/allow-override.conf; \
  a2enconf allow-override

# Copia o app (vamos usar bind-mount no compose, então isto é só fallback)
COPY . /var/www/html/

# Opcional: timezone
RUN ln -snf /usr/share/zoneinfo/America/Sao_Paulo /etc/localtime && echo America/Sao_Paulo > /etc/timezone

# Porta padrão do Apache já é 80

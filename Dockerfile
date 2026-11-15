# DormFinder container for Vercel's container runtime (or any Docker host). FrankenPHP = Caddy + PHP.
FROM dunglas/frankenphp:1-php8.4-alpine

RUN install-php-extensions pdo_mysql fileinfo opcache mbstring

# Hosted defaults: no local disk, so sessions and photos live in the database. Override in the host's environment settings.
ENV SESSION_DRIVER=database \
    DORMFINDER_STORAGE_DRIVER=database \
    TRUST_PROXY=1 \
    DB_SSL_CA=/etc/ssl/certs/ca-certificates.crt

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-dormfinder.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /app
COPY . /app
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

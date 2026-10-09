FROM php:8.3-fpm-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql intl zip bcmath pcntl redis opcache \
    && apk add --no-cache git unzip postgresql17-client

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

# O volume nomeado de vendor/ herda este dono na primeira montagem.
RUN mkdir -p /var/www/html/vendor && chown www-data:www-data /var/www/html/vendor

COPY entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint \
    && sed -i 's/^user = www-data/;user = www-data/; s/^group = www-data/;group = www-data/' /usr/local/etc/php-fpm.d/www.conf
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

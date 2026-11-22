FROM php:8.2-cli

RUN docker-php-ext-install pdo pdo_mysql opcache

RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.memory_consumption=32'; \
    echo 'opcache.max_accelerated_files=1000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.jit=0'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

ENV PHP_CLI_SERVER_WORKERS=2

WORKDIR /app
COPY . /app
RUN chmod +x /app/start.sh

EXPOSE 8080

CMD ["/app/start.sh"]

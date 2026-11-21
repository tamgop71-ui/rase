FROM php:8.2-cli

# Устанавливаем PDO MySQL и OPcache + JIT для максимальной скорости выполнения
RUN docker-php-ext-install pdo pdo_mysql opcache

RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.memory_consumption=64'; \
    echo 'opcache.max_accelerated_files=2000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.jit_buffer_size=32M'; \
    echo 'opcache.jit=tracing'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# 8 параллельных воркеров встроенного сервера PHP
ENV PHP_CLI_SERVER_WORKERS=8

WORKDIR /app
COPY . /app
RUN chmod +x /app/start.sh

EXPOSE 8080

CMD ["/app/start.sh"]

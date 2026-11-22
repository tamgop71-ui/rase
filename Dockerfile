FROM php:8.2-cli

RUN docker-php-ext-install pdo pdo_mysql opcache

# Стабильная настройка OPcache (без JIT, который вызывает падения воркеров php -S)
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.memory_consumption=64'; \
    echo 'opcache.max_accelerated_files=2000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.jit=0'; \
    echo 'max_execution_time=15'; \
    echo 'default_socket_timeout=5'; \
} > /usr/local/etc/php/conf.d/bot-stable.ini

# 4 параллельных воркера — оптимальный баланс скорости и лимита соединений MySQL
ENV PHP_CLI_SERVER_WORKERS=4

WORKDIR /app
COPY . /app
RUN chmod +x /app/start.sh

EXPOSE 8080

CMD ["/app/start.sh"]

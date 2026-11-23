FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        fonts-dejavu-core \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql opcache gd \
    && rm -rf /var/lib/apt/lists/*

# Жёсткие таймауты сокетов и mysqlnd (убирает зависания при обрыве NAT/MySQL)
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.memory_consumption=64'; \
    echo 'opcache.max_accelerated_files=2000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.jit=0'; \
    echo 'max_execution_time=15'; \
    echo 'default_socket_timeout=5'; \
    echo 'mysqlnd.net_read_timeout=5'; \
    echo 'memory_limit=128M'; \
} > /usr/local/etc/php/conf.d/bot-stable.ini

# 4 параллельных воркера — оптимальный баланс скорости и лимита соединений MySQL
ENV PHP_CLI_SERVER_WORKERS=4

WORKDIR /app
COPY . /app
RUN chmod +x /app/start.sh

EXPOSE 8080

CMD ["/app/start.sh"]

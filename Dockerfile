FROM php:8.2-cli

# Устанавливаем PDO MySQL и OPcache для максимальной скорости выполнения PHP
RUN docker-php-ext-install pdo pdo_mysql opcache

# Настройка OPcache для мгновенного отклика скриптов
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.memory_consumption=64'; \
    echo 'opcache.max_accelerated_files=2000'; \
    echo 'opcache.validate_timestamps=0'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# 4 параллельных воркера встроенного сервера (бот не тормозит при одновременных командах)
ENV PHP_CLI_SERVER_WORKERS=4

WORKDIR /app
COPY . /app

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "bot.php"]

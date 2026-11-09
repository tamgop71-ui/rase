FROM php:8.2-cli

# Устанавливаем драйверы MySQL для подключения к внешней БД
RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /app
COPY . /app

EXPOSE 8080

# Запуск встроенного сервера PHP, перенаправляющего запросы на bot.php
CMD ["php", "-S", "0.0.0.0:8080", "bot.php"]

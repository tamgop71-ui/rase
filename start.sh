#!/bin/sh
php /app/worker.php &
exec php -S 0.0.0.0:8080 /app/bot.php

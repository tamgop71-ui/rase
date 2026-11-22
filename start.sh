#!/bin/sh
# Самовосстанавливающийся фоновый воркер (перезапускается автоматически при сбое)
(
  while true; do
    php /app/worker.php
    echo "[WATCHDOG] worker.php exited, restarting in 3s..." >&2
    sleep 3
  done
) &

# Самовосстанавливающийся веб-сервер PHP
while true; do
  php -S 0.0.0.0:8080 /app/bot.php
  echo "[WATCHDOG] php server exited, restarting in 2s..." >&2
  sleep 2
done

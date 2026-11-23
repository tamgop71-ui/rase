#!/bin/sh
# 1. Самовосстанавливающийся фоновый демон (напоминания, розыгрыши, автосинк, отчёты)
(
  while true; do
    php /app/worker.php &
    WPID=$!
    echo "$WPID" > /tmp/worker.pid
    wait "$WPID"
    echo "[WATCHDOG] worker.php exited, restarting in 3s..." >&2
    sleep 3
  done
) &

# 2. Активный Healthcheck-сторож (проверяет HTTP-сервер и worker.php каждые 20 сек)
(
  sleep 10
  HTTP_FAILS=0
  while true; do
    sleep 20

    # Проверка отклика HTTP-сервера с таймаутом 4 сек
    if php -r '$c=@stream_context_create(["http"=>["timeout"=>4]]); $r=@file_get_contents("http://127.0.0.1:8080/health",false,$c); exit(trim((string)$r)==="ok"?0:1);'; then
      HTTP_FAILS=0
    else
      HTTP_FAILS=$((HTTP_FAILS + 1))
      echo "[WATCHDOG] HTTP healthcheck failed ($HTTP_FAILS/2)" >&2
      if [ "$HTTP_FAILS" -ge 2 ]; then
        echo "[WATCHDOG] HTTP server frozen! Force restarting php -S..." >&2
        if [ -f /tmp/http.pid ]; then
          kill -9 "$(cat /tmp/http.pid)" 2>/dev/null || true
        fi
        pkill -9 -f "php -S 0.0.0.0:8080" 2>/dev/null || true
        HTTP_FAILS=0
      fi
    fi

    # Проверка пульса worker.php (если завис > 90 сек — принудительный перезапуск)
    if [ -f /tmp/worker_hb.txt ] && [ -f /tmp/worker.pid ]; then
      HB=$(cat /tmp/worker_hb.txt 2>/dev/null || echo 0)
      NOW=$(date +%s)
      DIFF=$((NOW - HB))
      if [ "$DIFF" -gt 90 ]; then
        echo "[WATCHDOG] worker.php stalled (${DIFF}s), restarting..." >&2
        kill -9 "$(cat /tmp/worker.pid)" 2>/dev/null || true
      fi
    fi
  done
) &

# 3. Основной веб-сервер PHP с автоматическим перезапуском
while true; do
  php -S 0.0.0.0:8080 /app/bot.php &
  HPID=$!
  echo "$HPID" > /tmp/http.pid
  wait "$HPID"
  echo "[WATCHDOG] php -S exited, restarting in 1s..." >&2
  sleep 1
done

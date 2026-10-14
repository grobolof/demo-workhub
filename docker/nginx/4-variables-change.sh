#!/bin/bash
# Подставляет APP_HOST и APP_PATH из окружения в nginx.conf.
# В образе лежит шаблон с плейсхолдерами — без подстановки Nginx слушал бы буквально $APP_HOST.
# Каталоги шаблонов Bitrix `.default` не должны попадать под запрет скрытых файлов,
# иначе админка остаётся без стилей грида.

log info "Подставляю домен и путь сайта в конфиг Nginx…"
envsubst '$APP_HOST $APP_PATH' < /etc/nginx/conf.d/nginx.conf > /tmp/nginx.conf
sed -i 's#location ~ /\\\. {#location ~ /\\.(?!default(?:/|$)) {#' /tmp/nginx.conf
python3 - <<'PY'
from pathlib import Path

path = Path("/tmp/nginx.conf")
text = path.read_text()
if "workhub_demo_restore.php" in text:
    raise SystemExit(0)

needle = "    location ~ \\.php$ {"
block = """    # Только команда «Восстановить демо»: без лимита времени. Остальные PHP-запросы не меняются.
    location ~ ^/bitrix/admin/workhub_demo_restore\\.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_buffering off;
        gzip off;
        fastcgi_read_timeout 49d;
        fastcgi_send_timeout 49d;
        send_timeout 49d;
        proxy_read_timeout 49d;
        fastcgi_param PHP_VALUE "
            max_execution_time = 0
            max_input_time = -1
            memory_limit = 256M
            post_max_size = 100M
            upload_max_filesize = 100M
            date.timezone = Europe/Moscow
        ";
    }

"""
if needle not in text:
    raise SystemExit("php location missing")
path.write_text(text.replace(needle, block + needle, 1))
PY
mv -f /tmp/nginx.conf /etc/nginx/conf.d/nginx.conf
log success "Конфиг Nginx обновлён"

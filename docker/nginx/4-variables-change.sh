#!/bin/bash
# Подставляет APP_HOST и APP_PATH из окружения в nginx.conf.
# В образе лежит шаблон с плейсхолдерами — без подстановки Nginx слушал бы буквально $APP_HOST.
# Каталоги шаблонов Bitrix `.default` не должны попадать под запрет скрытых файлов,
# иначе админка остаётся без стилей грида.

log info "Подставляю домен и путь сайта в конфиг Nginx…"
envsubst '$APP_HOST $APP_PATH' < /etc/nginx/conf.d/nginx.conf > /tmp/nginx.conf
sed -i 's#location ~ /\\\. {#location ~ /\\.(?!default(?:/|$)) {#' /tmp/nginx.conf
mv -f /tmp/nginx.conf /etc/nginx/conf.d/nginx.conf
log success "Конфиг Nginx обновлён"

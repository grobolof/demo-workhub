include .env

# Запустить контейнеры:
up:
	docker compose up -d --build --remove-orphans

# Остановить контейнеры:
stop:
	docker compose stop

# Остановить и удалить контейнеры:
down:
	docker compose down -v

# Очистить директории кэша:
cache-clear:
	docker compose exec -it application bash -c "rm -rf bitrix/cache && rm -rf bitrix/managed_cache && rm -rf bitrix/stack_cache"

# КОМАНДЫ ДОСТУПНЫ ПРИ УСЛОВИИ ЧТО УСТАНОВЛЕН МОДУЛЬ МИГРАЦИЙ:

## Создать файл миграций:
migrations-create:
	docker compose exec -it application bash -c 'php bin/migrate add &&  chmod -R 777 local/php_interface/migrations'

## Накатить все миграции:
migrations-up:
	docker compose exec -it application php bin/migrate up

## Откатить все миграции:
migrations-down:
	docker compose exec -it application php bin/migrate down

## Откатить определенную миграцию (рекумендуется использовать только на последней выполненной миграции):
migrations-down-version: ## Пример: make migrations-down-version VERSION=Version20260303232408
	docker compose exec -it application php bin/migrate down $(VERSION)

# КОМАНДА ДОСТУПНА ПРИ УСЛОВИИ ЧТО УСТАНОВЛЕН ПАКЕТ PHPSTAN:

## Проверить код на ошибки:
phpstan:
	docker compose exec -it application php vendor/bin/phpstan analyse

# КОМАНДЫ ДОСТУПНЫ ПРИ УСЛОВИИ ЧТО УСТАНОВЛЕН ПАКЕТ PHPCS:

## Исправить ошибки:
phpcs:
	docker compose exec -it application php vendor/bin/php-cs-fixer fix --allow-risky=yes

## Проверить код на ошибки без исправления:
phpcs-dry:
	docker compose exec -it application php vendor/bin/php-cs-fixer fix --allow-risky=yes --dry-run || true

# КОМАНДЫ ДОСТУПНЫ ПРИ УСЛОВИИ ЧТО УСТАНОВЛЕН ПАКЕТ RECTOR:

## Исправить ошибки:
rector:
	docker compose exec -it application php vendor/bin/rector process

## Проверить код на ошибки без исправления:
rector-dry:
	docker compose exec -it application php vendor/bin/rector process --dry-run || true

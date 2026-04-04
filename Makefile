.PHONY: up down build rebuild shell migrate seed fresh tinker test lint

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

rebuild:
	docker compose build --no-cache

shell:
	docker compose exec php bash

migrate:
	docker compose exec php php artisan migrate

seed:
	docker compose exec php php artisan db:seed

fresh:
	docker compose exec php php artisan migrate:fresh --seed

tinker:
	docker compose exec php php artisan tinker

test:
	docker compose exec php php artisan test

lint:
	docker compose exec php vendor/bin/pint --test

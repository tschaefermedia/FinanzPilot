.PHONY: up down build rebuild shell migrate seed fresh tinker backup test lint

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

rebuild:
	docker compose build --no-cache

shell:
	docker compose exec app bash

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

fresh:
	docker compose exec app php artisan migrate:fresh --seed

tinker:
	docker compose exec app php artisan tinker

backup:
	docker compose exec app php artisan backup:database

test:
	php artisan test

lint:
	vendor/bin/pint --test

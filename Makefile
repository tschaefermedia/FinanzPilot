.PHONY: up down build shell migrate seed fresh tinker npm-install vite-build

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

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

npm-install:
	docker compose exec node npm install

vite-build:
	docker compose exec node npm run build

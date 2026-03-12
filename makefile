install:
	docker compose run --rm app composer install

update:
	docker compose run --rm app composer update

format:
	docker compose run --rm app vendor/bin/php-cs-fixer fix

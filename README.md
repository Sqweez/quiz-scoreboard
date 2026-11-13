# Таблица квиза

Laravel + Inertia + Vue монолит для ведения игр, команд, раундов и баллов.
PostgreSQL хранит пользователей, игры и сессии. Supabase нужен только как
источник одноразового переноса старых данных.

## Запуск на VPS

Нужны Docker Engine, Compose и HTTPS reverse proxy на VPS.

```sh
cp .env.example .env
# Заполнить APP_URL, DB_PASSWORD и TRUSTED_PROXIES в приватном .env.
docker compose build
docker compose run --rm --no-deps app php artisan key:generate --show
# Сохранить полученный APP_KEY в .env один раз; не менять между выпусками.
docker compose up -d postgres
docker compose run --rm app php artisan migrate --force
docker compose up -d
curl --fail http://127.0.0.1:8087/api/health
```

Приложение слушает только loopback; внешний proxy направляет HTTPS-трафик
на `127.0.0.1:8087`. PostgreSQL наружу не публикуется. Не открывайте запись
пользователям до финального импорта и сверки.

- [Развёртывание, обновление и резервные копии](docs/deployment.md).
- [Перенос пользователей и данных из Supabase](docs/data-migration.md).
- [Согласованная спецификация](docs/superpowers/specs/2026-11-13-laravel-monolith-design.md).

## Локальная разработка

PHP 8.4 с `pdo_pgsql`, Composer, Node 24 и PostgreSQL. Для локального
запуска в `.env` установить `APP_ENV=local`, `APP_URL=http://localhost:8000`,
`DB_HOST=127.0.0.1`, параметры своей локальной БД и `SESSION_SECURE_COOKIE=false`.

```sh
composer install
npm ci
php artisan key:generate
php artisan migrate
php artisan serve
# В другом терминале:
npm run dev
```

## Проверки

PHP feature-тесты используют две отдельные локальные PostgreSQL-базы:
`quiz_test` и `quiz_source` на порту `55439`, пользователь/пароль `quiz`.
Эти базы тесты очищают: никогда не направлять их на рабочие данные.

```sh
docker run -d --name quiz-laravel-tests -e POSTGRES_USER=quiz -e POSTGRES_PASSWORD=quiz -e POSTGRES_DB=quiz_test -p 127.0.0.1:55439:5432 postgres:17-alpine
# После готовности PostgreSQL:
docker exec quiz-laravel-tests createdb -U quiz quiz_source
php artisan test
npm test
npm run typecheck
npm run build
docker compose config --quiet
```

Playwright не используется. Реальный production-перенос считается выполненным
только после импорта и сверки целевой базы на VPS.

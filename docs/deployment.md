# Развёртывание на VPS

## Окружение и первый запуск

Скопировать `.env.example` в `.env`, установить права `chmod 600 .env`,
указать домен, длинный уникальный `DB_PASSWORD` и IP/CIDR доверенного
reverse proxy. Пароли с символом `$` в dotenv заключать в одинарные кавычки.
Не использовать `TRUSTED_PROXIES=*`: указывать адрес proxy в том виде,
в котором Nginx/PHP видит его через Docker bridge. Его можно определить
по access log Nginx при запросе через домен. На VPS с TLS-proxy на хосте
обычно это gateway Docker-сети, а не публичный IP посетителя.

```sh
docker compose build
docker compose run --rm --no-deps app php artisan key:generate --show
```

Сохранить полученный ключ в приватный `APP_KEY`. Это секрет приложения;
не публиковать его. Генерация с `--show` выводит ключ для записи в окружение,
не изменяет `.env` внутри образа. Ключ не пересоздаётся при обновлении.

```sh
docker compose up -d postgres
docker compose run --rm app php artisan migrate --force
docker compose up -d
docker compose ps
curl --fail http://127.0.0.1:8087/api/health
```

Nginx healthcheck может быть красным до применения migrations.
Миграции выполняются отдельным шагом выпуска. PostgreSQL сохраняется в volume
`postgres_data`; `docker compose down` сохраняет данные, а
`docker compose down -v` удаляет volume и для рабочей базы недопустима.

## HTTPS proxy

Предполагается существующий reverse proxy VPS с выпущенным сертификатом.
Пример location в его HTTPS server-блоке Nginx:

```nginx
location / {
    proxy_pass http://127.0.0.1:8087;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
}
```

Публично открываются только 80/443 внешнего proxy. Порт приложения привязан
к loopback, порт PostgreSQL не публикуется. `APP_URL` содержит HTTPS-домен,
`SESSION_SECURE_COOKIE=true`. Для локального HTTP smoke-теста отдельно
использовать `SESSION_SECURE_COOKIE=false`. Копирование в браузере требует
HTTPS или localhost.

До публичного доступа выполнить [перенос данных](data-migration.md).
После импорта и проверки удалить `SUPABASE_SOURCE_URL` из рабочего `.env` и
пересоздать app: `docker compose up -d --force-recreate app`.

## Обновление

Сначала сохранить резервную копию и известный рабочий git commit/image.
Установить новую версию исходников, затем:

```sh
docker compose build
docker compose run --rm app php artisan migrate --force
docker compose up -d
curl --fail http://127.0.0.1:8087/api/health
docker compose logs --tail=100 app nginx
```

Production-образ содержит готовые Composer/Vite-артефакты. Node и dev server
при работе не запускаются. Нет Redis, websocket или фонового worker.
Не запускать `config:cache` при сборке: секреты передаются при запуске.
При изменении `.env` нужен новый app-контейнер, а не только restart.

## Резервная копия и восстановление

Выполнять копии по cron VPS, сохраняя архивы вне volume PostgreSQL и
копируя на отдельный сервер/хранилище. Пример создания одного архива:

```sh
umask 077
mkdir -p /srv/backups/quiz
docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc --no-owner --no-acl' > /srv/backups/quiz/quiz.dump
```

Не перезаписывать единственный рабочий архив; в cron использовать уникальное
имя по дате и контролировать exit code команды. Отдельно безопасно
сохранить `.env`/APP_KEY: дамп БД не содержит ключ приложения.

Проверка восстановления в отдельную базу того же PostgreSQL:

```sh
docker compose exec -T postgres sh -c 'createdb -U "$POSTGRES_USER" quiz_restore_check'
docker compose exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d quiz_restore_check --no-owner --no-acl --exit-on-error' < /srv/backups/quiz/quiz.dump
docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d quiz_restore_check -c "SELECT count(*) FROM games; SELECT count(*) FROM scores;"'
```

Проверять также чтение данных приложением, направленным на восстановленную
базу. Не восстанавливать архив поверх работающей production-базы. Порядок
финального переключения и ограничения отката описаны в инструкции импорта.

## Диагностика

```sh
docker compose ps
docker compose logs --tail=100 app nginx postgres
curl --fail http://127.0.0.1:8087/api/health
```

HTTP health проверяет Laravel и запрос к БД. Healthcheck app проверяет ответ
PHP-FPM ping. Ошибки приложения идут в stderr; production debug выключен.

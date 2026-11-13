# Production: quiz.ariesdev.kz

Сервер — `ssh-iron` (`root@affectionate-benz.194-4-56-226.plesk.page`).
Рабочий каталог — `/srv/quiz`. Деплой повторяет схему `fitness-backend`:
GitHub Actions → GHCR → SSH → Docker Compose. Plesk в деплое, настройке домена
и продлении сертификата не участвует.

## Автоматический выпуск

`.github/workflows/deploy.yml` запускается при push в `migrate/laravel-monolith`
и `main`. Пока миграция проверяется, `main` остаётся со старым Nuxt-приложением.
Ручной `workflow_dispatch` появится в интерфейсе после попадания workflow в
основную ветку. Workflow проверяет frontend, TypeScript, сборку и PHP-тесты
на синтетическом PostgreSQL, затем собирает два amd64-образа:

- `ghcr.io/sqweez/quiz-scoreboard-app:<commit SHA>`
- `ghcr.io/sqweez/quiz-scoreboard-nginx:<commit SHA>`

На сервер передаются Compose и временный `GITHUB_TOKEN` для чтения GHCR.
Регистрационные данные Docker хранятся во временном закрытом каталоге и
удаляются при завершении. Отдельный SSH-ключ Actions ограничен командой
`/srv/quiz/deploy.sh`; shell, forwarding и PTY для него запрещены. В GitHub
Secrets сохранены `SSH_KEY`, `SSH_KNOWN_HOSTS`, `SSH_HOST`, `SSH_USER`.
Ключ хоста получен через уже проверенное SSH-подключение.

Скрипт блокирует параллельные выпуски через `flock`, скачивает образы,
сохраняет дамп существующей БД перед миграциями, выполняет `migrate --force`,
ждёт healthy-контейнеров и проверяет HTTP health. Только после этого обновляет
`current` и `previous`. Последний шаг Actions проверяет публичный HTTPS health.
При ошибке миграций/health workflow завершается ошибкой; автоматического отката
схемы или данных нет. Первый выпуск до импорта имеет пустую целевую БД.

## Серверные файлы

- `/srv/quiz/.env`: APP_KEY, пароль БД, HTTPS URL и доверенный gateway
  `172.30.87.1`; права 600. Эти значения сохраняются между выпусками.
- `/srv/quiz/releases/<SHA>/compose.production.yaml`: конфигурация выпуска.
- `/srv/quiz/current`, `/srv/quiz/previous`: ссылки на успешные выпуски.
- `/srv/quiz/deploy.sh`, `/srv/quiz/backup.sh`: копии скриптов из
  `docker/production/`, устанавливаемые администратором, права 700.
- `/srv/quiz/backups`: дампы PostgreSQL; хранение 14 дней.
- `/etc/nginx/conf.d/quiz.conf`: отдельный конфиг только этого домена,
  исходник — `docker/production/quiz.nginx.conf`.
- `/srv/quiz/tls`: сертификат и ключ; продление существующим cron `acme.sh`.
- `/srv/quiz/acme`: HTTP webroot для проверки владения доменом.

Compose-проект называется `quiz-scoreboard`. PostgreSQL хранится в отдельном
volume `quiz-scoreboard_postgres_data`. Порт БД не публикуется; Nginx контейнера
доступен только на `127.0.0.1:8087`. HTTPS принимает Nginx хоста.
Не запускать `docker compose down -v`: это удалит рабочую БД.

Сертификат установлен через:

```sh
/root/.acme.sh/acme.sh --issue --server letsencrypt -d quiz.ariesdev.kz -w /srv/quiz/acme --keylength ec-256
/root/.acme.sh/acme.sh --install-cert -d quiz.ariesdev.kz --ecc \
  --key-file /srv/quiz/tls/key.pem --fullchain-file /srv/quiz/tls/fullchain.pem \
  --reloadcmd 'nginx -t && systemctl reload nginx'
```

Уже существующий cron acme.sh выполняет продление ежедневно. Скрипты Actions
не меняют конфиги Nginx/сертификат/секреты. После изменения серверных скриптов
или Nginx администратор отдельно устанавливает их актуальную версию;
перед reload обязателен `nginx -t`.

## Диагностика и резервные копии

В административном SSH-сеансе:

```sh
cd /srv/quiz
export APP_ENV_FILE=/srv/quiz/.env
export IMAGE_TAG=$(basename "$(readlink -f current)")
docker compose -p quiz-scoreboard --env-file .env -f current/compose.production.yaml ps
docker compose -p quiz-scoreboard --env-file .env -f current/compose.production.yaml logs --tail=100 app nginx postgres
curl --fail https://quiz.ariesdev.kz/api/health
/srv/quiz/backup.sh
```

Ежедневный дамп запускается из `/etc/cron.d/quiz-backup`. Перед каждым
последующим выпуском создаётся дополнительный дамп. Хранение на том же VPS
не защищает от потери сервера: копируйте дампы и `.env` в отдельное закрытое
хранилище. APP_KEY необходим отдельно от базы данных. Образы/слои других
проектов не удаляются; при нехватке диска удалять только подтверждённые старые
образы квиза, оставляя текущий и предыдущий SHA.

Проверка восстановления в отдельную БД (не поверх production):

```sh
# После установки переменных и Compose-команды из примера выше:
docker compose -p quiz-scoreboard --env-file .env -f current/compose.production.yaml \
  exec -T postgres sh -c 'createdb -U "$POSTGRES_USER" quiz_restore_check'
docker compose -p quiz-scoreboard --env-file .env -f current/compose.production.yaml \
  exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d quiz_restore_check --no-owner --no-acl --exit-on-error' < backups/SELECTED.dump
```

## Откат к предыдущему коду

Остановить новые записи и проверить совместимость предыдущего кода с текущей
схемой. Сохранить свежий дамп. В административном SSH-сеансе:

```sh
cd /srv/quiz
export APP_ENV_FILE=/srv/quiz/.env
export IMAGE_TAG=$(basename "$(readlink -f previous)")
docker compose -p quiz-scoreboard --env-file .env -f previous/compose.production.yaml up -d --wait --wait-timeout 120
curl --fail https://quiz.ariesdev.kz/api/health
# После успешной проверки сохранить текущую ссылку и переключить current на previous.
```

Дамп автоматически не восстанавливается: это потеряло бы новые записи.
Для повторного выпуска конкретного коммита можно перезапустить его успешный
Actions run, пока образы этого SHA доступны. Подробности первичного импорта
и ограничений возврата к Supabase — в [инструкции переноса](data-migration.md).

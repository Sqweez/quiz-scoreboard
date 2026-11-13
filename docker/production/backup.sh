#!/usr/bin/env bash
set -euo pipefail
umask 077

cd /srv/quiz
export APP_ENV_FILE=/srv/quiz/.env
export IMAGE_TAG
IMAGE_TAG=$(basename "$(readlink -f current)")
mkdir -p backups
backup="backups/quiz-$(date -u +%Y%m%dT%H%M%S)-$$.dump"
docker compose -p quiz-scoreboard --env-file .env -f current/compose.production.yaml \
  exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc --no-owner --no-acl' > "$backup.tmp"
mv "$backup.tmp" "$backup"
find backups -name 'quiz-*.dump' -mtime +14 -delete
echo "Saved $backup"

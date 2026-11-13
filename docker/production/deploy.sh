#!/usr/bin/env bash
set -euo pipefail
umask 077

# Installed by the administrator; the Actions key can invoke only this command.
if [[ ! ${SSH_ORIGINAL_COMMAND:-} =~ ^deploy\ ([a-f0-9]{40})$ ]]; then
  echo 'Expected: deploy <commit SHA>' >&2
  exit 1
fi
release=${BASH_REMATCH[1]}
cd /srv/quiz
exec 9>deploy.lock
flock -n 9
export APP_ENV_FILE=/srv/quiz/.env
export IMAGE_TAG="$release"
export DOCKER_CONFIG
DOCKER_CONFIG=$(mktemp -d /srv/quiz/registry.XXXXXX)
trap 'rm -rf "$DOCKER_CONFIG"' EXIT
tar -xzf - -C "$DOCKER_CONFIG" compose.production.yaml registry-user registry-token
docker login ghcr.io -u "$(cat "$DOCKER_CONFIG/registry-user")" --password-stdin < "$DOCKER_CONFIG/registry-token"
rm "$DOCKER_CONFIG/registry-token"
mkdir -p "releases/$release"
mv "$DOCKER_CONFIG/compose.production.yaml" "releases/$release/compose.production.yaml"
compose=(docker compose -p quiz-scoreboard --env-file .env -f "releases/$release/compose.production.yaml")
"${compose[@]}" config --quiet
"${compose[@]}" pull
if [[ -L current ]]; then
  /srv/quiz/backup.sh
fi
"${compose[@]}" up -d --wait --wait-timeout 120 postgres
"${compose[@]}" run --rm -T --no-deps app php artisan migrate --force
"${compose[@]}" up -d --wait --wait-timeout 120 postgres app nginx
# Scheduler has no healthcheck, so --wait would fail on it.
"${compose[@]}" up -d scheduler
curl --fail --silent --show-error http://127.0.0.1:8087/api/health
if [[ -L current && $(readlink current) != "releases/$release" ]]; then
  ln -sfn "$(readlink current)" previous
fi
ln -sfn "releases/$release" current
echo "Deployed $release"

# GitHub Actions deployment

**Goal:** Deploy the Laravel monolith to `quiz.ariesdev.kz` on `ssh-iron`, preserving Supabase data.

**Architecture:** Follow `Sqweez/fitness-backend`: GitHub Actions builds amd64 images in GHCR and deploys over SSH using Compose. Use immutable commit tags, a dedicated SSH key restricted to the quiz deploy command, and temporary registry credentials. Host Nginx and acme.sh handle HTTPS independently of Plesk. Production lives in `/srv/quiz`; PostgreSQL has its own volume and network.

- [x] Add `.github/workflows/deploy.yml`: frontend/backend checks, Docker builds, SSH deployment, public HTTPS health check.
- [x] Add `compose.production.yaml`: image-only deployment, isolated PostgreSQL, loopback port 8087, resource/log limits.
- [x] Add `docker/production/deploy.sh` and `backup.sh`: serialize releases, back up before migrations, check health, keep current/previous release pointers. Never downgrade the database automatically.
- [x] Add domain Nginx configuration, issue and install the certificate with existing acme.sh, retain automatic renewal.
- [x] Configure private production environment and a dedicated deploy key; upload GitHub secrets without logging them.
- [x] Validate the workflow locally and push the migration branch without triggering Actions. Production runs only after push/merge to `main`; main merge authorized and completed.
- [x] Import a fresh read-only Supabase snapshot, compare all fields, remove source credentials, verify backup restoration, then open HTTPS traffic.
- [x] Document actual deployment, rollback and backup commands; verify other running projects remain healthy.

# Laravel Monolith Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans for sequential implementation and superpowers:subagent-driven-development for isolated tasks and reviews. Track results below.

**Goal:** Replace Nuxt/Supabase with a Docker-hosted Laravel monolith while retaining all existing functionality and providing a verified transfer of accounts and games.

**Architecture:** Laravel serves Inertia Vue pages and the existing internal JSON contract under one origin. PostgreSQL stores users, games and sessions. An explicit import command reads a consistent source snapshot, preserves identifiers and hashes, and verifies every imported row before commit.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia Vue, Vite, Pinia, PostgreSQL, PHPUnit, Vitest, Docker Compose.

## 1. Bootstrap without changing the existing deployment

- [x] Run existing `npm test`: baseline is 13 passing tests.
- [x] Create isolated branch/worktree `migrate/laravel-monolith` at `/private/tmp/quiz-scoreboard-laravel`; preserve the user's modified CLAUDE.md in the original checkout.
- [ ] Move Vue source `app/` to `resources/js/` to free Laravel's `app/` namespace; keep `shared/quiz/` unchanged.
- [ ] Copy the official Laravel skeleton (artisan, bootstrap, config, routes, public/index.php, storage, composer.json, phpunit.xml); install PHP dependencies with PHP 8.4.
- [ ] Add Inertia middleware, Blade root, Vite entry, explicit aliases, session and CSRF setup.

## 2. Data model and game operations

Files: `database/migrations/*`, `app/Models/{User,Game,Team,Round,Score}.php`, `app/Services/QuizService.php`, `app/Http/Controllers/{Game,Auth}Controller.php`, `routes/web.php`, `tests/Feature/{Quiz,Auth}Test.php`.

- [ ] First write HTTP tests for anonymous access, account registration/login/logout, ownership, creation, fractional batch saves, invalid team/round IDs, finished game immutability and team/round editing.
- [ ] Run `php artisan test --filter='QuizTest|AuthTest'` and verify routes are missing before implementation.
- [ ] Create UUID users plus the four existing tables with original precision, indexes, cascade constraints and database sessions. Use the existing Game JSON format without a `data` wrapper.
- [ ] Implement authenticated routes, transactions, input validation, normalized scores and status metadata; serialize ordered relationships with no N+1.
- [ ] Run the feature tests against PostgreSQL; expand focused assertions where existing behavior requires it.

## 3. Import and field-level verification (release blocker)

Files: `app/Console/Commands/ImportSupabase.php`, `app/Services/SupabaseTransfer.php`, `config/database.php`, `tests/Feature/SupabaseTransferTest.php`, `docs/deployment.md`.

- [ ] First test preservation of UUIDs, password hashes, timestamps, decimals and relationships; refuse nonempty target, duplicate emails, orphans, unknown status and incompatible password hashes without partial writes.
- [ ] Source is a separately restored Supabase PostgreSQL snapshot, configured through an isolated `supabase_source` connection. Never import the source's sessions or system auth objects.
- [ ] Implement `php artisan supabase:import` with a read-only repeatable-read source transaction and a single target transaction. Copy users, games, rounds, teams, scores in dependency order, without Eloquent mutators or automatic timestamps.
- [ ] Compare all mapped field values and row counts before commit. `--verify-only` compares an already imported target against the frozen source, without writes. Tests must catch changed values even when counts match.
- [ ] Verify imported password login through Laravel with a synthetic known password. Never request or log real plaintext passwords.
- [ ] Run all import tests on PostgreSQL and document snapshot preparation, import, verification and switching back before writes reopen.

## 4. Preserve the Vue experience

Files: `resources/js/{app.ts,pages/**,stores/quiz.ts,lib/http.ts}`, `vite.config.ts`, `tsconfig.json`, `package.json`, `tests/unit/*`.

- [ ] Replace Nuxt links/routing/autoimports with Inertia and explicit Vue imports, retaining templates, styles and components.
- [ ] Provide CSRF-aware JSON requests and read Laravel's `message` errors; keep the existing store queue, debounce and revisions.
- [ ] Use server authentication props for the homepage and login; POST login/register/logout to Laravel. Clear user-specific state on logout.
- [ ] Flush pending score changes before Inertia navigation; preserve game-id changes and prevent late responses from changing the active game.
- [ ] Adapt unit tests to explicit HTTP imports; run `npm test`, `npm run typecheck`, `npm run build`.

## 5. Docker and deployment

Files: `Dockerfile`, `compose.yaml`, `.dockerignore`, `docker/nginx.conf`, `.env.example`, `README.md`, `docs/deployment.md`, `AGENTS.md`.

- [ ] Build Vite and Composer in separate stages; runtime is PHP-FPM with pdo_pgsql and OPcache, plus Nginx with compiled assets and a persistent PostgreSQL volume.
- [ ] Publish only Nginx on a loopback HTTP port for the VPS TLS proxy; configure proxy trust explicitly. Database has no public port; health checks and restart policies are present.
- [ ] Add production env example and exact commands for build, key generation, migrations, health, backups, restore, source snapshot import and final cutover.
- [ ] Validate `docker compose config`, build images and start a disposable Compose project with isolated volume and port.
- [ ] Check HTTP health, session login/CSRF, protected page and asset delivery using HTTP requests, without Playwright.

## 6. Review and handoff

- [ ] Remove only superseded Nuxt/Prisma files and dependencies after replacement tests pass; preserve unrelated user changes.
- [ ] Review against the approved specification, then review code quality with particular attention to import atomicity, complete verification and session/CSRF integration.
- [ ] Run fresh PHP, Vitest, TypeScript, build and Compose checks. Record exact outcomes; do not claim real accounts have moved without access to the real source and destination.
- [ ] Commit reviewed code on the migration branch; leave original checkout intact until integration is requested.
- [ ] Request the source snapshot/access and VPS deployment details only for the actual rehearsal/deployment steps that require them. Local synthetic import is not proof of real data migration.

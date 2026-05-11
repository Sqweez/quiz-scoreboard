# Repository Guidelines

## Project Structure & Module Organization

This is a minimal Nuxt 4 application. The app entry point is `app/app.vue`; add routed pages under `app/pages/`, shared Vue components under `app/components/`, and composables under `app/composables/` as the project grows. Static files that should be served as-is belong in `public/`, such as `public/favicon.ico` and `public/robots.txt`. Core project configuration lives in `nuxt.config.ts`, with TypeScript references in `tsconfig.json`.

## Build, Test, and Development Commands

Use npm, since `package-lock.json` is committed.

- `npm install`: install dependencies and run Nuxt preparation through `postinstall`.
- `npm run dev`: start the local development server, usually at `http://localhost:3000`.
- `npm run build`: create a production build with `nuxt build`.
- `npm run preview`: preview the built application locally.
- `npm run generate`: generate static output for compatible deployments.

## Coding Style & Naming Conventions

Use Vue single-file components with `<script setup lang="ts">` when component logic is needed. Keep indentation at two spaces, matching the existing Vue and config files. Name Vue components in PascalCase, such as `ScoreBoard.vue`; name composables with the `use` prefix, such as `useQuizScores.ts`. Prefer clear TypeScript types for shared state and props. No formatter or linter is currently configured, so keep changes small and consistent with Nuxt defaults.

## Testing Guidelines

No test framework is configured yet. For new behavior, add focused tests before relying on manual checks; Vitest with `@vue/test-utils` is the expected fit for Nuxt/Vue unit tests. Place tests near the code or in a future `tests/` directory, using names like `ScoreBoard.test.ts`. Until tests are added, verify changes with `npm run build` and a local `npm run dev` smoke test.
Do not test it via playwright, if i don't ask about it

## Commit & Pull Request Guidelines

This repository has no existing commits to infer a local convention from. Use concise, imperative commit messages such as `Add quiz scoreboard layout` or `Configure Vitest`. Pull requests should include a short summary, verification steps, linked issues when available, and screenshots for visible UI changes. Keep each PR scoped to one feature or fix.

## Security & Configuration Tips

Do not commit secrets or machine-specific `.env` files. Document required environment variables in a committed example file if configuration is added later.

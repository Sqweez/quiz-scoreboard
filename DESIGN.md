# Design

Visual system for the quiz scoreboard. The tool runs a paper-feel
score-keeping experience for one operator at a bar/pub table. Numbers and
team names are the primary visual material; everything else is quiet
chrome that gets out of the way.

## Theme

**Light only.** Single warm paper-white surface. No dark variant yet
(the `@custom-variant dark` hook is declared but unused; remove it or
implement a dark theme when the use case appears).

Physical scene that fixed this decision: «счётчик паб-квиза за столом
с ноутбуком, локальное освещение от лампы, два пива по бокам». Local
light, not ambient gloom; light theme wins.

## Color

Strategy: **Restrained.** Tinted neutrals plus a single accent that
covers under 10% of any surface.

All values in OKLCH, declared in `app/assets/css/main.css` as bare
CSS custom properties and exposed to Tailwind via `@theme inline` so
they generate the standard color utilities (`bg-primary`, `text-rule-strong`,
`border-rule`, etc.). **Never inline `[var(--*)]` arbitrary values; the
named utilities exist for everything.**

| Token | OKLCH | Role |
|---|---|---|
| `--background` / `bg-background` | `0.985 0.006 80` | Page background, warm paper-white. |
| `--foreground` / `text-foreground` | `0.22 0.012 70` | Body text, graphite. |
| `--card` | same as background | Legacy shadcn alias. Cards are flat in this system. |
| `--primary` / `bg-primary` / `text-primary` | `0.55 0.18 27` | The single accent. Warm red (FT/Kicker family). Used on the leader marker, focus rings, primary CTA, destructive emphasis, error text. |
| `--primary-foreground` | `0.985 0.006 80` | Text on primary fills. |
| `--secondary` / `bg-secondary` | `0.94 0.008 75` | Quiet surface for zebra rows, error backgrounds, ghost buttons. |
| `--muted` / `--accent` | `0.93–0.94 0.008–0.01 75` | Hover wash for interactive items. |
| `--muted-foreground` / `text-muted-foreground` | `0.48 0.012 70` | Secondary text, captions, eyebrows. |
| `--destructive` | `0.55 0.18 27` | Same as primary by design. Single accent serves both positive (leader) and high-stakes (delete) signals; context disambiguates. |
| `--border` / `border-border` | `0.86 0.01 75` | Form-element borders, page-section hairlines. |
| `--input` | `0.86 0.01 75` | Form input borders. |
| `--ring` | `0.55 0.18 27` | Focus ring color (primary). |
| `--rule` / `border-rule` | `0.9 0.008 75` | Hairlines inside tables and list dividers. |
| `--rule-strong` / `border-rule-strong` | `0.42 0.012 70` | Strong horizontal rules under section headers and above tables. |
| `--leader` / `bg-leader` | `0.55 0.18 27` | Aliased primary, reserved for the leader marker so future themes can split. |

## Typography

Two families. **Spectral** carries the editorial weight; the system sans
handles UI labels and form text.

- **Serif (`font-serif` / `font-display`):** Spectral 400/500/700 via
  Google Fonts. Loaded from `nuxt.config.ts` head links with `preconnect`
  warm-up. Tabular numerals via `font-feature-settings: 'lnum', 'tnum'`
  on `.font-display`. Used for: page titles (`text-4xl`–`text-5xl`),
  team names, scores (Σ column, score cells), section subheadings,
  rank numbers in the table.
- **Sans (`font-sans`):** Inter / system stack. Tabular nums globally
  on body. Used for: navigation, buttons, eyebrow labels, form inputs,
  sync/copy status, error text.
- **Mono (`font-mono`):** SFMono stack. Reserved; not currently used.

Body has `font-variant-numeric: tabular-nums` globally so digits align
in any prose context.

Scale (no fluid clamp; product UI is fixed-rem):
- Title: `text-4xl` / `text-5xl lg:` (mobile/desktop) for game titles
- Subhead: `text-2xl`–`text-3xl` for section subtitles and Σ values
- Body: `text-base`
- Caption / dense form labels: `text-sm`
- Eyebrow: 11px (see `.eyebrow` utility)

## Utility classes

Defined under `@layer utilities` in `app/assets/css/main.css`. Use
these by name; do not re-create the literals.

| Class | What | When |
|---|---|---|
| `.font-display` | Spectral + lining/tabular numerals + tighter letter-spacing (`-0.015em`). | Any prominent serif text: titles, team names, scores. |
| `.font-serif` | Spectral + lining/tabular numerals. | Less prominent serif (subheadings inside sections). |
| `.eyebrow` | `font-size: 11px; letter-spacing: 0.18em; text-transform: uppercase;`. Color NOT set, attach a Tailwind color class. | Page-level eyebrows: «Таблица квиза», «Идут сейчас», status badges. |
| `.eyebrow-tight` | Same as `.eyebrow` but `letter-spacing: 0.14em`. | In-table column headers and dense form labels. |
| `.editable` | Invisible background + transparent bottom border that turns to `--rule-strong` on hover/focus. | Any inline-edit input that visually masquerades as text (game title, team name, round name). Pair with serif/display typography. |

## Layout

- **Page container:** `mx-auto max-w-Nxl` with `px-4 lg:px-8`. Widths by
  surface:
  - Score-game page: `max-w-5xl`
  - Games index: `max-w-5xl`
  - Edit, New, GameSetup: `max-w-3xl`
  - Login: `max-w-md`
- **Vertical rhythm:** `gap-10` between major page sections, `gap-6`
  inside sections, `space-y-3` between header eyebrow and h1, `space-y-4`
  inside form groups. Don't equalize paddings: rhythm is part of the
  system.
- **Section header pattern:** eyebrow (text-muted-foreground) + h1
  (font-display) + optional description (text-sm text-muted-foreground),
  followed by `border-b border-rule-strong pb-6`. Replicated on every
  top-level surface.
- **Subsection header pattern:** small h2 with eyebrow label + count,
  followed by `border-b border-rule-strong pb-2`. Used inside editors
  and on the games index.
- **No card wrappers.** Sections sit on the page background with
  hairlines above/below. Do not introduce shadows, rounded containers,
  or background tints for grouping.
- **Border radius:** `--radius: 0.25rem` everywhere. Buttons and inputs
  are softly tactile, not pill-shaped.

## Components

### Button (`~/components/ui/button`)
shadcn-vue, six variants via CVA. New tokens propagate automatically.
Use:
- `variant="default"` for the primary CTA on a surface. There is only
  one primary CTA per screen at any time. Currently used for: «Создать
  игру», «Начать игру», «Скопировать результаты», «Войти», «Создать
  аккаунт».
- `variant="outline"` for secondary actions: «Добавить» in editors.
- `variant="ghost"` for very subdued affordances inside forms.
- `variant="destructive"`: same paint as default (single accent). Avoid
  for non-destructive contexts.

### Input, Label, Spinner
shadcn-vue defaults. Spinner now takes a `label` prop, default
«Загрузка», localized.

### Table (`~/components/ui/table`)
shadcn-vue primitives kept for generic uses. The score table uses a
plain `<table>` directly to control density and typography precisely.

### ScoreTable (`~/components/ScoreTable.vue`)
The page-defining component. Bare `<table>`, no card wrapper,
hairlines via `border-rule` between rows, zebra striping with
`bg-secondary/35` on odd rows, vertical hairline between team and
score columns.

Key behaviors:
- **Leader marker:** small red dot (`bg-leader` 6×6) before the rank
  number, semibold team name. No row tint, no amber.
- **Sticky context bar:** announces «Редактируем · <team> · раунд N» at
  the top of the viewport whenever a score input is focused. Fades in
  150 ms and out after a 200 ms grace period so Tab between cells does
  not flicker.
- **Per-cell tooltip:** small dark badge above the focused cell with
  the team name. Driven by Tailwind `peer-focus`.
- **Row focus-within:** the active row gets a subtle `bg-primary/[0.07]`
  tint to confirm spatial position peripherally.
- **Input behavior:** auto-selects value on focus, blur after Enter,
  ArrowUp/Down suppressed, wheel scroll blurs the field.

### TeamsEditor, RoundsEditor, GameSetupForm
Editorial list rows on hairline dividers. Delete actions use an
inline-confirm pattern (see Patterns below). The setup form on the
new-game page uses the same row vocabulary.

### Eyebrow (utility class, not a component)
Use `.eyebrow` or `.eyebrow-tight`. Do not wrap in a component; the
copy varies too much per surface and the class is one line.

## Patterns

### Inline confirm for destructive actions
Replaces `window.confirm()`. Implementation pattern (repeated in three
spots: TeamsEditor, RoundsEditor, `games/[id]/index.vue`):

```ts
const confirmingId = ref<string | null>(null) // or boolean for single action

function requestDelete(id: string) { confirmingId.value = id }
function cancelDelete() { confirmingId.value = null }
function confirmDelete(id: string) { /* do delete */; cancelDelete() }
```

Visuals: a quiet «Удалить» trigger swaps to two visible controls,
«Подтвердить / Отмена» (or «Да, удалить / Отмена» for high-stakes
destructive actions). Primary token applies a colored underline on the
confirm button so the user can see what they're about to commit. Cancel
is a quiet ghost with an X icon.

Currently inlined per call site rather than abstracted: only three uses,
each with slightly different copy. Promote to a `<InlineConfirm>`
component if a fourth use lands.

### Section header with eyebrow + h1 + hairline
The repeating page header pattern across all top-level surfaces:

```vue
<header class="border-b border-rule-strong pb-6">
  <p class="eyebrow text-muted-foreground">Раздел</p>
  <h1 class="mt-3 font-display text-4xl font-medium leading-tight lg:text-5xl">
    Заголовок
  </h1>
  <p class="mt-3 max-w-xl text-sm text-muted-foreground">
    Опциональное пояснение.
  </p>
</header>
```

Kept as inline pattern (not a component): the right-side content varies
heavily across screens (CTA button, action group, inline-editable
title), and a component would either ossify those variations or proxy
slots indefinitely.

### Eyebrow + count on subsection headers
For lists with category labels and counts (games index, editors):

```vue
<div class="flex items-baseline justify-between gap-3 border-b border-rule-strong pb-2">
  <h2 class="eyebrow text-muted-foreground">Идут сейчас</h2>
  <span class="eyebrow text-muted-foreground">{{ count }}</span>
</div>
```

## Motion

- **Default timing:** 120–200 ms `transition-colors` on hover, focus,
  background changes. Sticky banner fades 150 ms.
- **Easing:** Tailwind defaults (`ease-out`) are acceptable. No bounce,
  no elastic, no spring.
- **No animation of layout properties.** Backdrop blur on the sticky
  banner is the heaviest effect and stays static once visible.
- **No reduced-motion handling yet.** Add when accessibility scope
  expands.

## Anti-patterns to refuse

Specific to this project, on top of the impeccable absolute bans:

- **Card wrappers around table or editor sections.** The visual language
  is hairlines on flat background; cards re-introduce the SaaS-admin
  reflex we removed.
- **Hero metric grid above the table.** Replaced explicitly in the
  redesign; do not re-add «Лидер / Команды / Раунды» stat cards.
- **Status pills** with uppercase tracking. The active status lives in
  the metaline as plain eyebrow text.
- **Amber / emerald / slate / red Tailwind palette literals.** Tokens
  exist for every state. The one warm red comes via `primary`/`leader`,
  never via `red-500` / `amber-400`.
- **`window.confirm()` and `window.alert()`.** Use inline-confirm.
- **Mixing serif and display fonts beyond Spectral.** Single editorial
  voice.
- **Adding a second icon set.** Lucide already in use; do not introduce
  Phosphor, Heroicons, or hand-rolled SVGs.

## Files of record

- Tokens, fonts, utility classes: `app/assets/css/main.css`
- Font loading: `nuxt.config.ts` (`app.head.link`)
- UI primitives: `app/components/ui/*`
- Score table: `app/components/ScoreTable.vue`
- Team / round editors: `app/components/TeamsEditor.vue`,
  `app/components/RoundsEditor.vue`
- Page templates: `app/pages/*.vue`, `app/pages/games/**/*.vue`
- Strategic context: `PRODUCT.md`

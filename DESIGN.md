# Design System — Portal Karyawan (Syifa Global Group)

Reference doc for the employee-facing portal UI (`resources/views/livewire/**`, `resources/views/components/**`, `resources/css/app.css`). Keep this updated when new shared patterns are introduced. Does **not** cover the Filament admin panel (`/admin`), which uses Filament's own theming.

## Stack

- Tailwind CSS v4 (`@import 'tailwindcss'` in `app.css`) + `@tailwindcss/forms`.
- Font: `Plus Jakarta Sans` (`body.site`). `--font-sans` in `@theme` is Instrument Sans but is not what the portal actually renders with — don't rely on it.
- Icons: Font Awesome 6 (`fa-solid`, `fa-regular`), loaded via CDN link in layout/login.
- Compiled via Vite. **The user runs `npm run watch`/`npm run build` themselves** — never run it yourself. If a style change "doesn't show up," the most likely cause is the compiled bundle (`public/build/assets/app-*.css`) is stale, not a code bug. Check file mtimes before assuming a bug.

## Hard rule: inline styles over new Tailwind utility classes

This codebase has been bitten by this repeatedly: newly-introduced Tailwind utility classes (`gap-x-*`, `mr-1.5`, arbitrary bracket values like `text-[19px]`, `shrink-0`, `lg:col-span-2`, etc.) do **not** reliably show up until a rebuild happens, and often the rebuild lags behind edits. Plain hand-written `style="..."` attributes always render immediately because they don't depend on Tailwind's content-scanning/JIT step.

**Convention for this project:** for anything beyond basic, already-proven-safe utility classes (`flex`, `items-center`, `justify-between`, `text-sm`, `text-slate-500`, `mb-6`, `p-5`, `rounded-xl`, `border`, `border-gray-200` — all confirmed working elsewhere), prefer inline `style="..."` for spacing, gaps, grids, and colors. This is why most component blade files in this project look inline-style-heavy rather than utility-class-heavy — it's deliberate, not sloppy.

Reusable, named visual identities (card families, badges, buttons) belong in `resources/css/app.css` as real CSS classes (see below) — those compile reliably since they're plain CSS, not Tailwind-generated utilities.

## Color palette

| Role | Hex | Usage |
|---|---|---|
| Primary / brand navy | `#042c6c` | Nav, primary buttons, links, focus rings, "current step" accents |
| Primary hover | `#032356` | Hover state of navy buttons/links |
| Success green | `#047C54` | Absensi/presence identity, success buttons, "done/approved" states |
| Rare accent magenta | `#B9308C` | Only in the top nav gradient line and a few decorative shapes — never a primary UI color |
| Slate neutrals | `#1e293b` (headings), `#334155`/`#374151` (body/values), `#64748b` (secondary/meta — **minimum** for any readable text), `#94a3b8` (decorative icons / native placeholders **only**, see contrast rule below) |
| Page background | `#f1f5f9` (`body.site`) |

### Contrast rule (do not regress this)

`#94a3b8` / Tailwind `text-slate-400` has ~2.8:1 contrast on white/light backgrounds — **fails WCAG AA**. This was audited and fixed across the whole app (2026-07-05). The rule going forward:

- Any text meant to be **read** (labels, captions, dates, table headers, empty-state messages, hints) must be `#64748b` (`text-slate-500`) or darker — never `#94a3b8`/`text-slate-400`.
- `#94a3b8` is only acceptable for genuinely decorative icon glyphs or native `input::placeholder` styling — not for actual text content.
- Identity/subtitle text that's more important than ambient meta (e.g. a job title under a person's name) should go one step darker still — `#475569`/`text-slate-600`.
- `.data-table thead th` in `app.css` is the single most load-bearing fix here — it controls every table header in the app.

## Card identity system

Each "domain" of the app gets **one** recognizable dark/colored gradient or solid card treatment, reused everywhere that domain appears (Dashboard, Karyawanku, etc.) so users recognize it at a glance without reading text. Don't reuse another domain's identity color for a new feature — give it its own if it's genuinely a new domain, but don't invent one for something that already fits an existing card family.

| Class | Domain | Look |
|---|---|---|
| `.welcome-card` | Greeting / quick actions | Navy gradient `#042c6c → #0b47a1`, white text, `.w-deco-*` decorative circles |
| `.absen-card` | Presence / clock-in (`.a-deco-*`) | Green gradient `#047C54 → #024732`, white text |
| `.approval-card` | "Butuh Persetujuan Anda" (pending approvals inbox) | Solid-feeling orange gradient `#9a3412 → #ea580c → #fb923c` (darker stop anchored under the header text for contrast), white text, `.appr-deco-*` |
| `.timeline-card` | Approval status timeline (`x-approval-timeline` component) | Solid soft yellow `#FCF0D6` with `#F0DBA3` border (no gradient — deliberately flat), **dark** text (`#422006` / `#374151` / `#64748b`), `.tl-deco-*` |
| `.neuro` | Saldo cuti / stat widgets | Light neumorphic double-shadow (`6px 6px 14px #c5ccd8, -6px -6px 14px #fff`), cool gray `#e8edf6` base |
| `.stat-card` | Plain white stat cards | Flat white, subtle shadow, `.stat-card-deco` faint corner circle |

All dark/colored identity cards follow the same anatomy: `border-radius:20px`, `position:relative;overflow:hidden`, 2–3 decorative circles/rotated-square shapes at low opacity (`.{prefix}-deco-1/2/3`), content wrapped in its own `position:relative;z-index:1` div so it sits above the decorations.

**Text-color rule per card type:** dark/saturated gradient cards (navy, green, dark-orange) → white text. Light/pastel cards (soft yellow timeline) → dark warm-toned text (`#422006` family), never white — white-on-yellow fails contrast immediately.

**Soft-UI (neumorphism) note:** neumorphism is inherently a low-color technique — the "raised from one surface" illusion depends on the element and background being close in hue/lightness, with depth coming from a light/dark shadow pair, not color. If a component needs both the soft-raised feel *and* a strong identity color, the working pattern from this app is: neutral raised surface (shadows carry the depth) + a colored **border ring** + a colored **icon** — not a solid color fill. See the timeline step badges (`approval-timeline.blade.php`) for the reference implementation.

## Components

- **Badges**: `.badge` + `.badge-approved` (green) / `.badge-pending` (amber) / `.badge-rejected` (red) / `.badge-cancelled` (gray). Always pair the two classes.
- **Buttons**: `.btn-primary` (solid navy), `.btn-ghost` (outlined gray, for light-background contexts), `.qa-btn` (translucent white pill, for buttons sitting on top of dark gradient cards — e.g. inside `.welcome-card`).
- **Tables**: `.data-table` — uppercase `#64748b` headers, hover row tint, borderless last row. Used for every list (Riwayat Pengajuan, Data Presensi, pending approvals, etc.)
- **Forms**: `.form-label`, `.form-input`, `.toggle`, `.time-grid`/`.date-grid` for paired inputs, `.upload-zone` for file drop areas. These came from the mockup HTML in `planning/web-attendance/demo/` and should stay consistent with that source design — don't reinvent form styling per page.
- **Field-row pattern** (label left, value right, hairline divider): used in the Karyawanku profile tab (`employee-attendance-statistics.blade.php`) for scannable label/value lists. Prefer this over floating label-above-value grid cells for anything with more than ~4 fields.

## Layout conventions

- Page header pattern: small `text-xs text-slate-500` date line, then `text-[22px] font-extrabold text-slate-900` page title, optional subtitle line.
- Tabs (when a page has multiple concerns, e.g. Karyawanku's Profil / Presensi & Absensi / Riwayat Pengajuan): plain Alpine (`x-data="{ tab: '...' }"`, `x-show`), styled with a bottom-border-underline active state — no extra JS library.
- Multi-card responsive grids: prefer `display:grid;grid-template-columns:repeat(auto-fit,minmax(Npx,1fr));gap:...` inline over `.card-pair`/bento-grid classes when the surrounding page isn't the Dashboard's bento grid — `.card-pair` specifically relies on `display:contents` + a parent bento grid with named `grid-column` placement classes (`bento-saldo-1`, etc.), and does nothing on its own outside that context.
- Dense multi-field data (profile info, employment info) uses the field-row pattern above, not a raw CSS grid of stacked label/value cells — better line-by-line scanability.

## Testing & safety (unrelated to visual design, but load-bearing)

- `phpunit.xml` must always stay `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`. Never point it at a real database — see `knowledge/changes/2026-07-05-*.md` for the incident this caused.
- Don't run or propose `php artisan test` unless explicitly asked, especially during rapid visual/CSS iteration — it's noise the user didn't request in that moment.

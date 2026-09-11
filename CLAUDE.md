# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

P-MPPD is a Laravel 12 + Filament 5 admin app for DPMPTSP Surabaya that manages **Surat Izin Praktik (SIP)** — practice-license submissions. The whole product is essentially one model (`SuratIzinPraktik`) wrapped in a Filament admin panel plus a single public upload page for applicants.

- PHP `^8.2`, Laravel `^12.0`, Filament `~5.0`
- DB: **PostgreSQL** (`p_mppd`) in dev/prod; SQLite `:memory:` in tests (see `phpunit.xml`)
- Tests: Pest 4; default queue/cache/session use the `database` driver
- Filament admin panel is mounted at the **site root** (`->path('')` in `AdminPanelProvider`) — there is no separate `/admin` prefix. The only non-Filament route is `/sip/upload/{record}` in `routes/web.php`.

## Commands

```bash
# All-in-one dev (php serve + queue listener + pail logs + vite)
composer dev

# Tests (clears config first, then runs Pest via artisan test)
composer test
php artisan test --filter=SomeTest        # single test / pattern

# Lint / format
./vendor/bin/pint

# Frontend
npm run dev        # vite dev server
npm run build      # production build (must run before deploy — Filament theme is compiled here)

# Initial setup (also defined in composer.json scripts.setup)
composer install && cp .env.example .env && php artisan key:generate \
  && php artisan migrate --force && npm install && npm run build
```

Note: `composer install`'s `post-autoload-dump` runs `php artisan filament:upgrade` automatically — expect Filament asset publishing on every install.

## Architecture

### The domain is one model
Everything orbits `App\Models\SuratIzinPraktik` (table `surat_izin_praktik`). Two things about it that affect almost every change:

1. **Route key is `nomor_register`** (not `id`) — `getRouteKeyName()` returns it. URLs like `/sip/upload/{record}` and Filament resource URLs resolve records by `nomor_register`, so it must stay unique (enforced by migration) and URL-safe.
2. **Two JSON columns drive the upload UX**: `kebutuhan_upload` (the *requirements* — array of `{name, type, description}`) and `document_upload` (the *submissions* — array of `{name, type, value, file, uploaded_at}`). `type` is one of `text | date | pdf | image | file`. Both are cast to `array` on the model.

The progress bar in `SuratIzinPraktikTable` and the dynamic form in `UploadDokumenSIP` both read these arrays — keep the shape consistent if you change one.

### Filament resource layout (Filament 5 split-class style)
`app/Filament/Resources/SuratIzinPraktik/` follows Filament 5's split-class layout — the resource class is thin and delegates to siblings:

- `SuratIzinPraktikResource.php` — wires everything together
- `Schemas/SuratIzinPraktikForm.php` — admin create/edit form
- `Schemas/SuratIzinPraktikInfolist.php` — view-mode layout
- `Tables/SuratIzinPraktikTable.php` — list columns/filters/actions
- `Pages/` — `List`, `Create`, `View`, `Edit`, plus a custom `ListSuratIzinPraktiksActivities` page that exposes the spatie activity log per record at `/{record}/activities`

When adding a field, you typically need to touch: the migration, the importer (`app/Filament/Imports/SuratIzinPraktikImporter.php`), the form schema, the infolist schema, and the table columns. The model itself rarely needs changes — no `$fillable`/`$guarded` is set, so attributes flow through.

### Status workflow
Status values are an implicit enum in code (no DB constraint): `masuk`, `proses`, `terverifikasi_teknis`, `ditolak_teknis`, `selesai`, `ditolak`, `dibatalkan`. Default is `masuk` (set in migration `2026_02_02_032405_alter_sip.php`). The same seven values are duplicated in three places — keep them in sync when adding/renaming:

- `ListSuratIzinPraktiks::getCounts()` and `getTabs()` (tab badges)
- `StatOverViewSuratIzinPraktik::getStats()` (dashboard widget)
- `SuratIzinPraktikPie` / `SuratIzinPraktikTrend` widgets

### Public upload page
`app/Filament/Pages/UploadDokumenSIP.php` is a `SimplePage` overriding `canAccess() => true` so it bypasses Filament auth. It's the *only* publicly reachable Filament page — be careful with what you expose there. It renders dynamic form fields built from `record->kebutuhan_upload` and writes results back to `document_upload` on `submit()`. The blade view lives at `resources/views/filament/pages/upload-dokumen-s-i-p.blade.php`.

### Importer behavior
`SuratIzinPraktikImporter::resolveRecord()` **skips existing rows** by checking `nomor_register`; it never updates. If you want upsert semantics, that method is where to change it. The importer is exposed via `ImportAction` only on the SIP list page.

### Activity log
`SuratIzinPraktik` uses `Spatie\Activitylog\Traits\LogsActivity` with `logOnlyDirty()->logAll()`. The activity log table is created by `2026_02_02_041909_create_activity_log_table.php` (plus two follow-ups for `event` and `batch_uuid` columns). The per-record activities page is what surfaces it in the UI; `pxlrbt/filament-activity-log` is also installed.

### Admin panel provider
`app/Providers/Filament/AdminPanelProvider.php` is the single source of truth for: panel path (root `''`), theme (`resources/css/filament/admin/theme.css`), explicitly-registered dashboard widgets (the three SIP widgets — auto-discovery is on, but they're also listed manually), middleware stack, and the `AuthUIEnhancerPlugin` login styling. Database notifications are enabled here too.

### App-level settings: `config/sip.php`
Tuning knobs for the SIP UX live in `config/sip.php` (each backed by an env var):

- `sip.hari_berjalan.success|info|warning` — threshold (hari) untuk eskalasi warna badge "Hari Berjalan" di tabel SIP. Default: `1 / 2 / 3` → di atas itu jadi `danger`.
- `sip.kedaluwarsa_window_days` — jendela hari untuk widget "SIP Akan Kedaluwarsa" (default 60).
- `sip.filter_akan_expired_days` — jendela hari untuk filter "SIP akan kedaluwarsa" di tabel utama (default 30).

Helper `SuratIzinPraktikTable::hariBerjalanColor($record)` adalah single source of truth untuk warna badge "Hari Berjalan"; widget lain (`BerkasTerbaruTable`) memanggilnya, jangan duplikasi logikanya.

## Conventions worth keeping

- Filament 5 split-class style for resources (Schemas / Tables / Pages in separate files) — don't collapse them back into the resource class.
- Locale of user-facing strings is Bahasa Indonesia (labels, notifications, status names). Keep new UI text consistent.
- Migration filenames are dated `2026_02_02_*` — these are the project's real migrations layered on top of Laravel's `0001_01_01_*` skeleton. New migrations should follow Laravel's `php artisan make:migration` naming.

# CLAUDE.md

Guidance for Claude Code when working in this package.

## What this is

`spaanproductions/laravel-switchbot-frame` — a standalone Laravel + Livewire admin page for the
SwitchBot AI Art Frame. It is a self-contained package: it ships its own compiled Tailwind CSS (served
by a package route) and does not depend on the host application's build, auth, SEO, or layout.

- PHP 8.4+, Laravel 12, Livewire 4.
- Built with `spatie/laravel-package-tools` (`PackageServiceProvider`).
- Tests run on Orchestra Testbench + PHPUnit.

## Commands

```bash
composer test        # PHPUnit via Testbench (vendor/bin/phpunit)
composer analyse     # PHPStan (larastan), level 5
composer format      # php-cs-fixer (PSR-12, tabs, length-sorted imports)
npm run build        # compile resources/css/switchbot-frame.css -> dist/switchbot-frame.css (Tailwind CLI)
```

Run a single test: `vendor/bin/phpunit --filter=test_name`.

## Architecture

```
src/
  LaravelSwitchbotFrameServiceProvider.php  # config, views, migrations, routes, Livewire components, publishes
  Api/                 # SwitchBotApi (cloud API client, HMAC) + SwitchBotException
  Actions/             # OptimizeImageForEink (pure GD e-ink optimizer)
  Jobs/                # OptimizeFrameImageJob (queued optimize)
  Livewire/            # Gallery (full page), NowShowing, WebhookManager
  Repositories/        # FrameStatusStore (DB-backed battery/status)
  Http/Controllers/    # ImageController (stream), WebhookController (receiver), StylesheetController (css)
  Console/Commands/    # ManageWebhook (switchbot:webhook)
  Enums/               # FrameImageStatus, FrameAspect
  Models/              # FrameImage, FrameStatus, SwitchBotWebhookLog
config/switchbot.php
database/{migrations,factories}/
resources/views/       # layout.blade.php (thin shell) + partials/ + livewire/
resources/css/switchbot-frame.css   # tailwind source  ->  dist/switchbot-frame.css (committed)
tests/                 # Testbench TestCase + Feature/Unit
```

Namespace root: `SpaanProductions\LaravelSwitchbotFrame\` → `src/`. Factories:
`SpaanProductions\LaravelSwitchbotFrame\Database\Factories\`. Tests: `…\Tests\`.

## Conventions

- **Indentation: tabs.** PSR-12, PHP 8 constructor promotion, explicit return types, imports sorted by
  length (php-cs-fixer enforces this — run `composer format`).
- Test methods are `snake_case`; every change must keep `composer test` green.
- Config keys stay under `config('switchbot.*')`. Route names are fixed: `switchbot.index`,
  `switchbot.images.show`, `switchbot.assets.css`, `switchbot.webhook`. The URL prefix, middleware and
  back-link are configurable in `config/switchbot.php`.
- The Livewire views render nested components by alias (`<livewire:switchbot.now-showing />`); these are
  registered explicitly in the service provider (the two-word `switchbot` namespace won't auto-resolve).

## Gotchas

- **After changing any Blade view, run `npm run build`** and commit `dist/switchbot-frame.css` — the
  standalone stylesheet is scanned from the views. New utility classes won't exist until you rebuild.
- The three `aspect-[3/4] | aspect-[4/3] | aspect-square` classes are chosen at runtime from config, so
  they are safelisted via `@source inline(...)` in `resources/css/switchbot-frame.css`.
- In `render()`, return views via `ViewFactory::make('switchbot::...')` (the `Illuminate\Support\Facades\View`
  facade), not the `view()` helper — larastan can't resolve namespaced package views through the helper.
- Migrations are **not** auto-loaded — the provider registers them via `publishesMigrations`, so hosts
  publish them (`vendor:publish --tag=switchbot-frame-migrations`, which stamps a fresh timestamp) per
  Laravel's recommendation. Tests load them via `defineDatabaseMigrations()` in the test `TestCase`.
- Publish tags all follow `switchbot-frame-*` (`-config`, `-views`, `-assets`, `-migrations`). Note spatie's
  `hasViews()` also registers a redundant `switchbot-views` tag; the `switchbot-frame-views` group is the
  documented one.
- larastan resolves the package via Testbench; `testbench.yaml` is intentionally absent (it would
  double-register the provider in tests). Do not add it.

# Contributing

This repo holds two independent Laravel projects — `restaurant-backend` and
`restaurant-customer-app` — see [`README.md`](README.md) for what each does
and [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for how they relate.

## Before you start

1. Read [`README.md`](README.md) for setup.
2. Read [`docs/PROJECT_STATE.md`](docs/PROJECT_STATE.md) to see what already
   exists and what's still pending — don't re-scaffold something that's
   already there, and don't silently change a documented decision (PHP
   version, database choice, auth model, etc.) without updating that file.
3. Check `composer.json` in the project you're touching for the actual
   installed versions before assuming what's available.
4. If your PHP CLI is 8.4 without `pdo_sqlite`, see the PHP version note in
   `docs/PROJECT_STATE.md` — both projects are developed and tested against
   PHP 8.3 in this environment.

## Workflow

1. One task, one focused change. Don't fold unrelated cleanups into a task
   that was scoped to something else.
2. Follow [`docs/CODING_STANDARDS.md`](docs/CODING_STANDARDS.md) for naming
   and code organization (Models, Services, Actions, DTOs, Enums, API
   Resources, Filament Resources).
3. If you touch `restaurant-backend`'s API, follow
   [`docs/API_CONVENTIONS.md`](docs/API_CONVENTIONS.md) — every endpoint
   returns the unified `{success, message, data|errors}` envelope via
   `App\Http\Responses\ApiResponse`, validates input through a Form Request,
   and authorizes through a Policy/Gate.
4. Write tests as you go, not after — see
   [`docs/TESTING.md`](docs/TESTING.md) for what's expected per kind of
   change.
5. Review [`docs/SECURITY.md`](docs/SECURITY.md) before touching auth, error
   handling, money, or anything that takes user input.

## Before you consider a change done

Run these from inside the project you changed
(`restaurant-backend/` or `restaurant-customer-app/`):

```bash
composer run lint:check   # Pint, fails on style violations without fixing them
composer run test         # lint:check + php artisan test
```

Or, to auto-fix style instead of just checking it:

```bash
composer run lint
```

If you changed both projects, run this in both.

## Commit hygiene

- Never commit a real `.env` file, credentials, or tokens — only
  `.env.example` (see `docs/SECURITY.md`).
- Never commit generated/build output that's already git-ignored (`vendor/`,
  `node_modules/`, `restaurant-customer-app/nativephp/` build artifacts,
  `public/build/`).
- Write commit messages that explain *why*, not just *what* — the diff
  already shows what changed.
- Don't leave a TODO without a short explanation of what's missing and why
  it wasn't done now.

## Keeping docs in sync

After a task that changes what exists or how it's structured, update
[`docs/PROJECT_STATE.md`](docs/PROJECT_STATE.md): what was added/changed,
how to run/test it, and any new decisions or assumptions made along the way.

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Running the queue worker

Customer push notifications (order status updates, payment status updates)
are dispatched as queued jobs — see `docs/NOTIFICATIONS.md` for the full
architecture. Nothing is actually delivered until a queue worker is
running to process them.

- **Local development**: `QUEUE_CONNECTION` defaults to `database` (see
  `.env`); start a worker in a separate terminal:

  ```bash
  php artisan queue:work
  ```

  Use `php artisan queue:listen` instead while actively developing —
  it auto-reloads code changes without needing a restart, at the cost of
  being slightly slower per job. `queue:work` does **not** pick up code
  changes automatically; restart it after deploying/pulling new code.

- **Production**: run `php artisan queue:work --tries=1` under a process
  monitor (Supervisor, systemd, etc.) so it's automatically restarted if it
  crashes or is stopped by a deploy. `--tries=1` is safe here because every
  queued job in this app manages its own `$tries`/`backoff()` explicitly
  (see `App\Jobs\SendCustomerPushNotificationJob`) rather than relying on
  the worker's default retry count.
- Failed jobs (all retries exhausted) land in the `failed_jobs` table —
  inspect with `php artisan queue:failed`, retry one with
  `php artisan queue:retry {id}` or all of them with
  `php artisan queue:retry all`.
- If the queue worker is ever stopped for an extended period, queued
  notifications simply wait in the `jobs` table until a worker picks them
  up again — nothing is lost, and `App\Models\NotificationDispatchLog`'s
  idempotency guard means a notification is never sent twice even if a job
  ends up being retried much later.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

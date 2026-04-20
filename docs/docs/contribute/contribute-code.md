---
id: contribute-code
sidebar_position: 1
title: Contribute Code
description: Workflow for shipping code changes to Colibri.
---

Colibri welcomes pull requests, especially when they arrive with context. Use this checklist as a starting point and tailor it to your needs; the goal is to make reviewers productive and deployments safe.

## 1. Explore the project structure

- `routes/api.php` defines API routes.
- `app/Http/Controllers/Api/` contains API controllers.
- `app/Http/Resources/` contains API resource transformers.
- `app/Repositories/` contains repository interfaces and Eloquent implementations.
- `app/Services/` contains business logic services.
- `app/Jobs/` contains queueable background jobs.
- `app/Console/Commands/` contains Artisan commands.
- `database/migrations/` contains schema migrations.
- `database/seeders/` contains data seeders.

Spend a few minutes reading the files around the functionality you plan to change. When in doubt, search for existing patterns and mimic them.

## 2. Set up your local environment

1. Duplicate `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```

2. Generate application key:
   ```bash
   php artisan key:generate
   ```

3. Configure SQLite in `.env`:
   ```
   DB_CONNECTION=sqlite
   # DB_DATABASE will default to database/database.sqlite
   ```

4. Run migrations and seeders:
   ```bash
   php artisan migrate --seed
   ```

5. (Optional) Configure AI services for description generation:
   - Add your API key to `config/ai.php` or via environment variables

## 3. Follow the coding conventions

- Use PHP 8.2+ features (constructor property promotion, readonly properties).
- Use explicit return type declarations and type hints.
- Controllers should be thin; business logic belongs in Services.
- Use API Resources to transform Eloquent models to JSON.
- Follow the repository pattern for data access.
- Prefer dependency injection over facades.
- Use Pest for testing (see `tests/` directory for examples).

## 4. Validate your change

1. Run tests:
   ```bash
   php artisan test --compact
   ```

2. Check code style:
   ```bash
   vendor/bin/pint
   ```

3. Update `docs/api/openapi.yaml` when you add or modify endpoints, then regenerate docs:
   ```bash
   cd docs && npm run gen-api
   ```

4. Build documentation to verify:
   ```bash
   cd docs && npm run build
   ```

## 5. Prepare the pull request

- Summarize the change.
- Link related issues or discussions.
- Include screenshots or API samples when you change user-facing behavior.
- Keep commits focused. Squash if you made multiple exploratory commits.

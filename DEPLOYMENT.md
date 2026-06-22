Overview

This repository is a PHP/MySQL web application. The project expects database credentials via `config/database.live.php` or environment variables (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_CHARSET`).

What I added

- `.env.example` — example environment variables.
- `Dockerfile` — container image (PHP 8.1 + Apache) with `mysqli` and `pdo_mysql` enabled.
- `docker-compose.yml` — local stack with MySQL for local testing.
- `.github/workflows/docker-image.yml` — builds and pushes image to GitHub Container Registry on push.

Notes about Vercel and PHP

- Vercel does not provide a native PHP runtime for full server-rendered PHP applications. Recommended options:
  - Use a container-based host (recommended): Render, Fly.io, Railway, or DigitalOcean App Platform. They accept Docker images and are straightforward for PHP apps.
  - If you must use Vercel, you would need to rewrite the app to a supported runtime (Node/Python) or run PHP behind an external service (not recommended).

Supabase (Postgres) considerations

- This app uses MySQL-specific SQL and `mysqli`. Supabase uses PostgreSQL. Migrating will require:
  - Converting SQL schema and data from MySQL -> Postgres (tools like `pgloader` or `mysql2pgsql` can help).
  - Replacing `mysqli` usage with `PDO` configured for Postgres, or using a compatibility layer.
  - Updating queries where MySQL-specific syntax is used (e.g., `AUTO_INCREMENT`, backticks, `ENGINE=` options, `INSERT ... ON DUPLICATE KEY UPDATE`).

Recommended deployment flow (container approach)

1. Create a GitHub repository and push this code.
2. Enable GitHub Actions (workflow added) to build image and push to GHCR.
3. Create an account on a container-friendly host (Render, Fly.io, Railway) and connect to GitHub or GHCR.
4. Set environment variables on the host (DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_CHARSET). For production DB, use managed MySQL (e.g., Render managed DB, PlanetScale, or RDS).

Local testing

- With Docker Compose:

```bash
# build and run
docker-compose up --build
# visit http://localhost:8000
```

Database migrations

- There are SQL files in the repo like `create_permissions_table.sql`. Use those to create schema on a MySQL instance.
- For Supabase/Postgres migration, export MySQL schema and use a converter (pgloader) or manual SQL adjustments.

Next steps I can do for you

- Add a `config/database.live.php.example` template for production.
- Convert SQL schema guidance or attempt an automated MySQL->Postgres translation for Supabase (requires review).
- Prepare a Render/Fly.io deploy manifest.

Tell me which of these you'd like me to do next.

Production config and container deploy manifests

- `config/database.live.php.example`: Copy to `config/database.live.php` and fill with production DB credentials. The app will detect and use this file in non-local environments (see `config/database.php`).
- `render.yaml`: A sample Render service manifest to deploy using the Dockerfile. Use the Render dashboard to connect your GitHub repo and set secret environment variables (DB_HOST, DB_USER, DB_PASS, DB_NAME).
- `fly.toml`: A minimal Fly.io app config. With `flyctl` you can run `fly launch` to create the app and deploy using the existing `Dockerfile`.

Deploy notes (Render / Fly.io)

1. Use a managed MySQL instance (Render managed DB, AWS RDS, PlanetScale, etc.) and set credentials in the host's environment variables or upload `config/database.live.php`.
2. For both platforms, point `DB_HOST` to the managed DB and set secure credentials. Do NOT commit `config/database.live.php` with secrets into git.

Supabase / Postgres notes

- This repository now supports a `DB_DRIVER` environment variable. To use Supabase/Postgres set `DB_DRIVER=pgsql` and provide `DB_HOST`, `DB_PORT` (usually 5432), `DB_USER`, `DB_PASS`, and `DB_NAME`.
- The application uses `PDO` for Postgres. Ensure `pdo` and `pdo_pgsql` PHP extensions are enabled on your host.
- You must convert the MySQL schema and data to Postgres-compatible SQL before importing into Supabase. Use tools like `pgloader` or `mysql2pgsql`, or request me to attempt an automated conversion (manual review required).

Quick Fly steps

```bash
# Install flyctl: https://fly.io/docs/hands-on/install-flyctl/
fly launch            # follow prompts (choose existing Dockerfile)
fly secrets set DB_HOST=... DB_USER=... DB_PASS=... DB_NAME=...
fly deploy
```

Quick Render steps

1. Create a new Web Service on Render and connect the GitHub repo.
2. Choose "Docker" as the environment and set `Dockerfile` path.
3. Add environment variables (DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_CHARSET).
4. Enable automatic deploys from `main` branch.

Security & Supabase reminder

- If you plan to use Supabase (Postgres), the app currently uses MySQL-specific code (`mysqli`) and MySQL-flavored SQL. Migrating requires converting the schema and code to Postgres-compatible SQL and a `PDO` or `pg_connect`-based database layer.

Files added in this step: `config/database.live.php.example`, `render.yaml`, `fly.toml`.
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
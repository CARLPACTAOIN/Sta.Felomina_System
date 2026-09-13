# Barangay Document System

Capstone review setup for the Web-Based Barangay Document Archiving, Verification, and Resident Profiling System.

## Quick Start With Docker

Requirements:

- Docker Desktop

Start the system:

```bash
docker compose up -d --build
```

Open:

```text
http://localhost:8080
```

On the first run, give Docker about 30-60 seconds to initialize PostgreSQL and run Laravel migrations/seeders before opening the browser.

The Docker setup starts three services:

- `nginx` serves the browser request on port `8080`
- `app` runs Laravel through PHP-FPM
- `db` runs PostgreSQL 16

On container startup, Laravel waits for PostgreSQL through Docker Compose health checks, then runs migrations, seeders, storage linking, and Laravel cache warmup.

## Default Review Accounts

Seeded accounts are managed by the application seeders:

```text
captain@stafelomina.local
secretary@stafelomina.local
purok1@stafelomina.local
```

Password for seeded review accounts:

```text
password
```

## Useful Commands

Stop the containers:

```bash
docker compose down
```

Reset the review database and uploaded files:

```bash
docker compose down -v
docker compose up -d --build
```

View application logs:

```bash
docker compose logs -f app
```

This Docker setup is intended for local capstone checking, not production hosting.

# Production Deployment, Docker Configuration, and Systems Maintenance Guide
## Barangay Sta. Felomina Document Archiving, Verification, and Resident Profiling System

---

## 1. System Overview & Architecture

The Barangay Sta. Felomina Document Archiving, Verification, and Resident Profiling System is an enterprise Laravel 11 application engineered for high availability, transactional data safety, and verifiable document workflows.

### Architectural Stack
- **Application Framework:** Laravel 11 (PHP 8.4 runtime)
- **Frontend Architecture:** Livewire 3 with Flux UI component library and Tailwind CSS (compiled via Vite)
- **Relational Database:** PostgreSQL 16 with ACID transactional guarantees
- **Web Server & Reverse Proxy:** Nginx 1.24+ / 1.27 (Docker)
- **File Storage:** Private disk storage (`storage/app/private`) for drafts and official PDF files; public storage (`storage/app/public`) linked via symlink
- **QR Generation Engine:** BaconQrCode SVG rendering vector streams on-demand without disk clutter

---

## 2. Infrastructure Prerequisites

Ensure target host environments meet the following minimum hardware and software prerequisites:

### Hardware Specifications
| Specification | Minimum Required | Recommended Production |
|---|---|---|
| CPU | 2 Cores (x86_64 or ARM64) | 4 Cores |
| Memory (RAM) | 2 GB | 4 GB to 8 GB |
| Storage | 20 GB SSD | 50 GB+ NVMe SSD |
| Network | 100 Mbps Ethernet | Gigabit Ethernet / Fiber |

### Software Prerequisites
- **Operating System:** Ubuntu 22.04 LTS / 24.04 LTS, Debian 12, or Windows 10/11 (Local Workstation)
- **PHP Runtime:** PHP 8.4 with mandatory extensions:
  - `pdo_pgsql`, `pgsql`, `zip`, `opcache`, `mbstring`, `xml`, `curl`, `bcmath`, `fileinfo`
- **Database Engine:** PostgreSQL 16 (with `postgresql-client` packages `pg_dump` and `psql`)
- **Web Server:** Nginx 1.24+ (or Apache 2.4 with mod_rewrite)
- **Dependency Managers:** Composer 2.x and Node.js 20+ (with npm)
- **Container Runtime (Optional):** Docker Engine 24+ with Docker Compose v2.20+

---

## 3. Option A: Docker Compose Deployment (Recommended)

Docker Compose provides a fully containerized, isolated production environment that guarantees environment consistency.

### 3.1 Service Topography
The containerized architecture defines three interconnected services in `docker-compose.yml`:
1. **`app` Service:** PHP 8.4-FPM container running the application core with `postgresql-client` installed. Mounts the persistent volume `app_storage` to `/var/www/html/storage`.
2. **`db` Service:** PostgreSQL 16 Alpine container with persistent volume `db_data` mounted to `/var/lib/postgresql/data` and automated healthchecks (`pg_isready`).
3. **`nginx` Service:** Nginx 1.27 Alpine web server container exposing HTTP port `8080:80` (or `80:80`), proxying PHP requests to `app:9000`.

### 3.2 Volume Persistence
Persistent named volumes prevent data loss across container rebuilds:
- `app_storage`: Preserves uploaded draft PDFs, immutable approved PDFs, and backup ZIP archives (`/var/www/html/storage`).
- `db_data`: Preserves PostgreSQL databases, tables, sequences, and transaction logs (`/var/lib/postgresql/data`).

### 3.3 Production Docker Environment Configuration
Create or edit your production `.env` file before launching containers:

```env
APP_NAME="Barangay Sta. Felomina System"
APP_ENV=production
APP_KEY=base64:yourGenerated32ByteKeyHere=
APP_DEBUG=false
APP_URL=https://records.stafelomina.gov.ph

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=barangay_system
DB_USERNAME=barangay
DB_PASSWORD=SecureProductionPostgresPassword123!

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

### 3.4 Container Launch & Provisioning Sequence
Run the following commands from the repository root:

```bash
# 1. Build and start containers in detached mode
docker compose up -d --build

# 2. Verify all services are running and db is healthy
docker compose ps

# 3. Generate application encryption key (if not already set in .env)
docker compose exec app php artisan key:generate --force

# 4. Run database migrations to construct schema
docker compose exec app php artisan migrate --force

# 5. Populate initial taxomony and administrative accounts
docker compose exec app php artisan db:seed --class=DocumentTaxonomySeeder --force
docker compose exec app php artisan db:seed --class=PurokSeeder --force
docker compose exec app php artisan db:seed --class=AccountSeeder --force

# 6. (Optional) For staging/demo environments, seed the full realistic dataset:
docker compose exec app php artisan db:seed --class=DemoDataSeeder --force

# 7. Establish storage symlink
docker compose exec app php artisan storage:link

# 8. Warm up Laravel configuration and route caches for maximum speed
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

---

## 4. Option B: Dedicated Linux Server Deployment (Ubuntu/Debian)

For bare-metal servers or virtual private servers (VPS) without Docker.

### 4.1 System Package Installation
Update apt repositories and install PHP 8.4, PostgreSQL 16, and Nginx:

```bash
sudo apt update && sudo apt upgrade -y

# Add Ondřej Surý PHP PPA
sudo apt install -y software-properties-common curl git unzip
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

# Install PHP 8.4 and required extensions
sudo apt install -y php8.4 php8.4-fpm php8.4-pgsql php8.4-zip php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-bcmath php8.4-opcache

# Install PostgreSQL 16 and client tools
sudo apt install -y postgresql postgresql-contrib postgresql-client

# Install Nginx web server
sudo apt install -y nginx

# Install Node.js 20 & Composer
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 4.2 PostgreSQL Database Provisioning
Log in as the postgres system user and configure the database and credentials:

```bash
sudo -u postgres psql
```

Execute SQL commands:
```sql
CREATE DATABASE barangay_system;
CREATE USER barangay WITH ENCRYPTED PASSWORD 'SecureProductionPostgresPassword123!';
GRANT ALL PRIVILEGES ON DATABASE barangay_system TO barangay;
ALTER DATABASE barangay_system OWNER TO barangay;
\q
```

### 4.3 Code Deployment & File Permissions
Clone the repository into the web root:

```bash
sudo mkdir -p /var/www/barangay-system
sudo chown -R $USER:$USER /var/www/barangay-system
git clone https://github.com/barangay/Barangay-System.git /var/www/barangay-system
cd /var/www/barangay-system
```

Install production PHP and Node dependencies:
```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

Configure directory permissions:
```bash
# Ensure storage and bootstrap/cache directories exist
mkdir -p storage/app/private storage/app/public storage/app/backups \
    storage/framework/cache storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache

# Set ownership to web server user (www-data)
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Create public storage symlink
php artisan storage:link
```

### 4.4 Environment Configuration (`.env`)
Create and edit `.env`:

```bash
cp .env.example .env
nano .env
```

Ensure essential production settings:
```env
APP_NAME="Barangay Sta. Felomina System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://records.stafelomina.gov.ph

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=barangay_system
DB_USERNAME=barangay
DB_PASSWORD=SecureProductionPostgresPassword123!
```

Generate the key and run migrations:
```bash
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=DocumentTaxonomySeeder --force
php artisan db:seed --class=PurokSeeder --force
php artisan db:seed --class=AccountSeeder --force

# Optimize performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4.5 Production Nginx Virtual Host Configuration
Create `/etc/nginx/sites-available/barangay-system`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name records.stafelomina.gov.ph;
    root /var/www/barangay-system/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php index.html;

    charset utf-8;
    client_max_body_size 25M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the configuration and reload Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/barangay-system /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 4.6 SSL / TLS Provisioning (Certbot)
Provision a free Let's Encrypt certificate:
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d records.stafelomina.gov.ph
```

---

## 5. Option C: Local Workstation Setup (Laragon / Windows CLI)

For local development or standalone offline operation on a Barangay Hall desktop computer:

1. **Install Laragon Full:**
   - Download and install Laragon (includes PHP 8.x and Nginx/Apache).
   - Ensure PHP 8.4 is selected in Laragon Menu > PHP > Version.
   - Install PostgreSQL 16 for Windows (from EnterpriseDB or Laragon packages).
2. **Extract / Clone Project:**
   - Place project into `C:\laragon\www\Barangay-System`.
3. **Database Configuration:**
   - Create database `barangay_system` using pgAdmin or `psql`.
   - Update `.env`:
     ```env
     DB_CONNECTION=pgsql
     DB_HOST=127.0.0.1
     DB_PORT=5432
     DB_DATABASE=barangay_system
     DB_USERNAME=postgres
     DB_PASSWORD=yourLocalPassword
     ```
4. **Initialize and Seed:**
   - Open Laragon Terminal:
     ```bash
     composer install
     npm ci
     npm run build
     php artisan key:generate
     php artisan migrate
     php artisan db:seed --class=DemoDataSeeder
     php artisan storage:link
     ```
5. **Start Services:**
   - Click **Start All** in Laragon, or run `php artisan serve --port=8080`.

---

## 6. Migration and Seeding Sequence

When initializing or updating the database, follow this strict sequence:

### Schema Construction
```bash
# Apply pending database schema migrations
php artisan migrate --force
```

### Taxonomy & Baseline Seeding
```bash
# Seed standard document types, categories, and priority rules
php artisan db:seed --class=DocumentTaxonomySeeder --force

# Seed the 7 official geopolitical Puroks
php artisan db:seed --class=PurokSeeder --force

# Seed administrative staff credentials
php artisan db:seed --class=AccountSeeder --force
```

### Comprehensive Demo Dataset Seeding
For testing, demonstration, or training purposes:
```bash
# Seed multi-year documents (2024-2026), all 7 lifecycle states, and Purok residents
php artisan db:seed --class=DemoDataSeeder --force
```
The `DemoDataSeeder` is completely idempotent and can be safely executed repeatedly without creating duplicate key conflicts.

---

## 7. Production Asset Compilation

Frontend styling (Tailwind CSS) and interactive UI behaviors (Flux UI / Livewire) are compiled using Vite:

```bash
# Clean install exact dependencies
npm ci

# Compile minified production bundles into public/build
npm run build
```

The resulting assets in `public/build` include hashed filenames referenced by Laravel's `@vite` directive in Blade layouts.

---

## 8. Ongoing Maintenance & Troubleshooting Runbooks

### 8.1 Database Connection Errors (`SQLSTATE[08006]`)
- **Symptom:** `Connection refused` or `password authentication failed for user "barangay"`.
- **Diagnosis:**
  - Verify PostgreSQL service status: `sudo systemctl status postgresql` or `docker compose ps db`.
  - Verify credentials in `.env` match `DB_PASSWORD` and `DB_USERNAME`.
  - Ensure PostgreSQL accepts TCP connections: check `listen_addresses = '*'` in `postgresql.conf` and `pg_hba.conf` client authentication rules.

### 8.2 Storage & Permission Denied Errors
- **Symptom:** `The path "storage/app/private/document-drafts" is not writable` or HTTP 500 when uploading PDFs.
- **Resolution:**
  - Re-assert ownership:
    ```bash
    sudo chown -R www-data:www-data /var/www/barangay-system/storage
    sudo chmod -R 775 /var/www/barangay-system/storage
    ```
  - Verify `php artisan storage:link` has created the public symlink:
    ```bash
    ls -l public/storage
    ```

### 8.3 PDF Streaming & Generation Issues
- **Symptom:** PDF preview fails or returns HTTP 404 on `/verify/{token}/document` or `/documents/{id}/draft-pdf`.
- **Resolution:**
  - Check file existence in private storage:
    `ls -la storage/app/private/document-officials/`
  - Verify PHP `fileinfo` and `zip` extensions are enabled:
    `php -m | grep -E 'fileinfo|zip'`
  - Ensure uploaded files do not exceed Nginx upload limits (`client_max_body_size 25M;` in Nginx config and `upload_max_filesize = 25M` in `php.ini`).

### 8.4 Vite Manifest Missing in Production (`Vite manifest not found`)
- **Symptom:** Web pages fail to load CSS/JS with error `Vite manifest not found at: .../public/build/manifest.json`.
- **Resolution:**
  - Rebuild production assets:
    ```bash
    npm ci && npm run build
    ```
  - Confirm `public/build/manifest.json` is present.

### 8.5 PHP-FPM 502 Bad Gateway
- **Symptom:** Nginx displays `502 Bad Gateway`.
- **Diagnosis:**
  - Verify PHP-FPM service is active: `sudo systemctl status php8.4-fpm`.
  - Check FPM socket path matches Nginx config: `/var/run/php/php8.4-fpm.sock`.
  - Inspect FPM error logs: `sudo tail -n 50 /var/log/php8.4-fpm.log`.

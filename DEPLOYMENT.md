# FinanzPilot — Deployment Guide

## Prerequisites

- Docker & Docker Compose installed on the host (Proxmox LXC, VM, or bare metal)
- (Optional) Cloudflare Tunnel or reverse proxy for HTTPS

---

## Option A: Deploy with Pre-built Image (Recommended)

The fastest way to deploy. Uses the pre-built image from GitHub Container Registry — no build step needed.

### 1. Create a project directory

```bash
mkdir finanzpilot && cd finanzpilot
```

### 2. Create docker-compose.yml

```yaml
services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - app-data:/var/www/html
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php

  php:
    image: ghcr.io/tschaefermedia/finanzpilot:latest
    volumes:
      - app-data:/var/www/html
      - ./.env:/var/www/html/.env
      - ./database:/var/www/html/database

volumes:
  app-data:
```

### 3. Create nginx.conf

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 4. Create .env

```env
APP_NAME=FinanzPilot
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=sqlite
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

### 5. Start and initialize

```bash
mkdir -p database
docker compose up -d
docker compose exec php composer install --no-dev --optimize-autoloader
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --force
docker compose exec php php artisan db:seed --force
```

The app is now running at `http://<your-server-ip>`.

### Updating to a new version

```bash
docker compose pull
docker compose up -d
docker compose exec php php artisan migrate --force
```

---

## Option B: Build from Source

Clone the repo and build the Docker image locally.

```bash
# 1. Clone the repository
git clone https://github.com/tschaefermedia/FinanzPilot.git
cd financial-pilot

# 2. Configure environment
cp .env.example .env
nano .env  # See "Environment Configuration" below

# 3. Build and start (frontend assets are built automatically via multi-stage Docker build)
docker compose build
docker compose up -d

# 4. Install PHP dependencies
docker compose exec php composer install --no-dev --optimize-autoloader

# 5. Generate application key
docker compose exec php php artisan key:generate

# 6. Run database migrations and seed categories
docker compose exec php php artisan migrate --force
docker compose exec php php artisan db:seed --force
```

### Updating from source

```bash
git pull
docker compose build
docker compose up -d
docker compose exec php composer install --no-dev --optimize-autoloader
docker compose exec php php artisan migrate --force
```

---

## Environment Configuration

```env
APP_NAME=FinanzPilot
APP_ENV=production
APP_DEBUG=false
APP_URL=https://finanzpilot.yourdomain.com

DB_CONNECTION=sqlite
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

AI is configured through the Settings page in the app (stored in the database, not .env).

---

## Docker Architecture

```
┌─────────────────────────────────────┐
│  Docker Compose                     │
│                                     │
│  ┌───────────┐  ┌──────────────┐  │
│  │   nginx   │  │     php      │  │
│  │  :80      │→ │    :9000     │  │
│  │  (proxy)  │  │  (fpm +      │  │
│  └───────────┘  │  built assets)│  │
│                  └──────────────┘  │
│                        │           │
│              database/database.sqlite │
└─────────────────────────────────────┘
```

| Service | Image | Purpose |
|---------|-------|---------|
| nginx | nginx:alpine | Reverse proxy, serves static files |
| php | ghcr.io/tschaefermedia/finanzpilot | Laravel app + pre-built frontend assets |

---

## Make Commands

When using Option B (build from source):

```bash
make up          # Start all containers
make down        # Stop all containers
make build       # Rebuild containers
make rebuild     # Rebuild containers without cache
make shell       # Open a bash shell in the PHP container
make migrate     # Run database migrations
make seed        # Run database seeders
make fresh       # Drop all tables, re-migrate, and re-seed
make tinker      # Open Laravel Tinker REPL
```

---

## Backup & Restore

FinanzPilot uses a single SQLite file. Backup is a file copy.

### Backup

```bash
# Option A (pre-built image)
docker compose exec php cp database/database.sqlite /var/www/html/storage/app/backup.sqlite
docker compose cp php:/var/www/html/storage/app/backup.sqlite ./finanzpilot-backup.sqlite

# Option B (from source)
cp database/database.sqlite /path/to/backup/finanzpilot-$(date +%Y%m%d).sqlite
```

### Restore

```bash
# Stop the app
docker compose down

# Option A: copy backup into volume
docker compose up -d
docker compose cp finanzpilot-backup.sqlite php:/var/www/html/database/database.sqlite
docker compose exec php chown www-data:www-data database/database.sqlite
docker compose restart

# Option B: direct file replacement
cp /path/to/backup/finanzpilot-20260403.sqlite database/database.sqlite
docker compose up -d
```

### Automated Backup (Cron)

```cron
# Daily backup at 2 AM (Option B)
0 2 * * * cp /path/to/financial-pilot/database/database.sqlite /path/to/backups/finanzpilot-$(date +\%Y\%m\%d).sqlite

# Keep only last 30 days
0 3 * * * find /path/to/backups/ -name "finanzpilot-*.sqlite" -mtime +30 -delete
```

---

## MCP Server

The MCP server runs as a separate Node.js process and connects directly to the SQLite database.

### Install dependencies

```bash
cd mcp-server
npm install
```

### Configure in Claude Code

The project includes a `.mcp.json` file. If using from a different location, add to your Claude Code MCP settings:

```json
{
  "mcpServers": {
    "finanzpilot": {
      "command": "node",
      "args": ["/path/to/financial-pilot/mcp-server/index.js"]
    }
  }
}
```

### Available MCP Resources

| Resource | URI | Description |
|----------|-----|-------------|
| Transactions | `finanzpilot://transactions` | Last 100 transactions |
| Categories | `finanzpilot://categories` | Category tree with totals |
| Summary | `finanzpilot://summary/current` | Current month summary |
| Loans | `finanzpilot://loans` | Active loans with paid amounts |
| Recurring | `finanzpilot://recurring` | Recurring templates |
| Balance | `finanzpilot://balance` | Total balance + 6-month trend |

### Available MCP Tools

| Tool | Description |
|------|-------------|
| `query_transactions` | Search/filter transactions by date, category, amount, text |
| `add_transaction` | Create a new manual transaction |
| `categorize_transaction` | Assign a category to a transaction |
| `add_rule` | Create a categorization rule |

---

## Recurring Transactions Scheduler

To auto-generate transactions from recurring templates, add the Laravel scheduler to the host's crontab:

```cron
* * * * * cd /path/to/financial-pilot && docker compose exec -T php php artisan schedule:run >> /dev/null 2>&1
```

This runs `recurring:generate` daily, creating transactions for all active templates with auto-generate enabled.

---

## Troubleshooting

### App shows blank page
```bash
# Check Laravel logs
docker compose exec php tail -50 storage/logs/laravel.log

# Ensure storage permissions (handled automatically by entrypoint, but just in case)
docker compose exec php chown -R www-data:www-data storage bootstrap/cache
```

### Database locked errors
```bash
# Verify WAL mode is active
docker compose exec php php artisan tinker --execute="DB::select('PRAGMA journal_mode')"
# Should return: wal
```

### Assets not loading
```bash
# Verify manifest exists
docker compose exec php ls -la public/build/manifest.json

# If missing, restart the PHP container (entrypoint copies assets)
docker compose restart php
```

### Port conflict
Change the nginx port in `docker-compose.yml`:
```yaml
ports:
  - "8080:80"
```

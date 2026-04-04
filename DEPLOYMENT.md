# FinanzPilot — Deployment Guide

## Prerequisites

- Docker & Docker Compose installed on the host (Proxmox LXC, VM, or bare metal)
- (Optional) Cloudflare Tunnel or reverse proxy for HTTPS

---

## Option A: Deploy with Pre-built Image (Recommended)

The fastest way to deploy. Uses the pre-built image from GitHub Container Registry — no build step needed. The repository includes a ready-to-use `docker-compose.prod.yml`.

### 1. Clone (or download) the repository

```bash
git clone https://github.com/tschaefermedia/FinanzPilot.git
cd financial-pilot
```

### 2. Set your APP_KEY (recommended)

> **Important:** The entrypoint auto-generates an `APP_KEY` on first start, but it only persists inside the Docker volume. If the volume is ever pruned, sessions and encrypted data become unreadable. For production, set it explicitly:

```bash
# Generate a key
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"

# Add it to docker-compose.prod.yml under php → environment:
#   - APP_KEY=base64:your-generated-key
```

### 3. Start

```bash
touch database.sqlite
docker compose -f docker-compose.prod.yml up -d
```

On first start, the entrypoint automatically:
- Creates `.env` from defaults
- Generates the application key (if not set via environment)
- Runs database migrations and seeds categories
- Sets all file permissions

The app is now running at `http://<your-server-ip>`.

### Updating to a new version

```bash
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

Migrations run automatically on container start — no manual steps needed.

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

## Environment Variables Reference

All variables can be set via Docker Compose `environment:`, a bind-mounted `.env` file, or the auto-generated `.env` inside the container.

### Application

| Variable     | Default            | Description                                             |
| ------------ | ------------------ | ------------------------------------------------------- |
| `APP_NAME`   | `FinanzPilot`      | Application name (shown in browser tab)                 |
| `APP_ENV`    | `production`       | Environment: `production` or `local`                    |
| `APP_KEY`    | *(auto-generated)* | Encryption key — see below |
| `APP_DEBUG`  | `false`            | Set `true` for detailed error pages (development only)  |
| `APP_URL`    | `http://localhost` | Public URL (used in exports, links)                     |
| `APP_LOCALE` | `de`               | Application locale                                      |

#### APP_KEY

The app key is used for encryption (sessions, cookies). It is auto-generated on first container start. You can also set it explicitly in your `docker-compose.yml`:

```yaml
environment:
  - APP_KEY=base64:your-key-here
```

To generate a key manually:

```bash
# Using PHP
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"

# Using OpenSSL
echo "base64:$(openssl rand -base64 32)"

# From a running container
docker compose exec php php artisan key:generate --show
```

Copy the output (e.g., `base64:RXxCXlba...`) and add it to `environment:` in your `docker-compose.yml`. Recommended for production — ensures the key persists across container recreations.

### Database

| Variable        | Default  | Description                                  |
| --------------- | -------- | -------------------------------------------- |
| `DB_CONNECTION` | `sqlite` | Database driver (only `sqlite` is supported) |

### Session & Cache

| Variable           | Default | Description                                |
| ------------------ | ------- | ------------------------------------------ |
| `SESSION_DRIVER`   | `file`  | Session storage: `file`, `cookie`, `array` |
| `SESSION_LIFETIME` | `120`   | Session timeout in minutes                 |
| `CACHE_STORE`      | `file`  | Cache backend: `file`, `array`             |
| `QUEUE_CONNECTION` | `sync`  | Queue driver: `sync` (recommended)         |

### Logging

| Variable      | Default  | Description                                            |
| ------------- | -------- | ------------------------------------------------------ |
| `LOG_CHANNEL` | `stack`  | Log channel                                            |
| `LOG_STACK`   | `single` | Stack channels                                         |
| `LOG_LEVEL`   | `error`  | Minimum log level: `debug`, `info`, `warning`, `error` |

### AI Configuration

AI provider settings are managed through the **Settings** page in the app and stored in the database — not in `.env`. Supported providers:
- **Claude** (Anthropic API)
- **OpenAI** / OpenAI-compatible APIs
- **Ollama** (local, fully offline)

---

## Docker Architecture

```
┌─────────────────────────────────────┐
│  Docker Compose                     │
│                                     │
│  ┌───────────┐   ┌───────────────┐  │
│  │   nginx   │   │     php       │  │
│  │  :80      │ → │    :9000      │  │
│  │  (proxy)  │   │  (fpm +       │  │
│  └───────────┘   │  built assets)│  │
│                  └───────────────┘  │
│                        │            │
│            database/database.sqlite │
└─────────────────────────────────────┘
```

| Service | Image                              | Purpose                                 |
| ------- | ---------------------------------- | --------------------------------------- |
| nginx   | nginx:alpine                       | Reverse proxy, serves static files      |
| php     | ghcr.io/tschaefermedia/finanzpilot | Laravel app + pre-built frontend assets |

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

| Resource     | URI                             | Description                    |
| ------------ | ------------------------------- | ------------------------------ |
| Transactions | `finanzpilot://transactions`    | Last 100 transactions          |
| Categories   | `finanzpilot://categories`      | Category tree with totals      |
| Summary      | `finanzpilot://summary/current` | Current month summary          |
| Loans        | `finanzpilot://loans`           | Active loans with paid amounts |
| Recurring    | `finanzpilot://recurring`       | Recurring templates            |
| Balance      | `finanzpilot://balance`         | Total balance + 6-month trend  |

### Available MCP Tools

| Tool                     | Description                                                |
| ------------------------ | ---------------------------------------------------------- |
| `query_transactions`     | Search/filter transactions by date, category, amount, text |
| `add_transaction`        | Create a new manual transaction                            |
| `categorize_transaction` | Assign a category to a transaction                         |
| `add_rule`               | Create a categorization rule                               |

---

## Recurring Transactions Scheduler

The Laravel scheduler runs automatically inside the container — no host-level cron needed. The entrypoint installs a cron job that runs `php artisan schedule:run` every minute, which triggers `recurring:generate` daily to create transactions from active recurring templates.

Scheduler output is logged to `storage/logs/scheduler.log` inside the container:

```bash
docker compose exec php tail -50 storage/logs/scheduler.log
```

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

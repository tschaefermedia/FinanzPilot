# FinanzPilot — Deployment Guide

## Prerequisites

- Docker & Docker Compose installed on the host (Proxmox LXC, VM, or bare metal)
- Git access to the repository
- (Optional) Cloudflare Tunnel or reverse proxy for HTTPS

---

## Quick Start

```bash
# 1. Clone the repository
git clone git@github.com:tschaefermedia/financial-pilot.git
cd financial-pilot

# 2. Configure environment
cp .env.example .env

# 3. Edit .env — see "Environment Configuration" below
nano .env

# 4. Build and start containers
docker compose build
docker compose up -d

# 5. Install PHP dependencies
docker compose exec php composer install --no-dev --optimize-autoloader

# 6. Generate application key
docker compose exec php php artisan key:generate

# 7. Build frontend assets
docker compose exec node npm install
docker compose exec node npm run build

# 8. Run database migrations and seed categories
docker compose exec php php artisan migrate --force
docker compose exec php php artisan db:seed --force

# 9. Set permissions
docker compose exec php chown -R www-data:www-data storage bootstrap/cache database
```

The app is now running at `http://<your-server-ip>`.

---

## Environment Configuration

Edit `.env` with these production values:

```env
APP_NAME=FinanzPilot
APP_ENV=production
APP_DEBUG=false
APP_URL=https://finanzpilot.yourdomain.com

# Database — SQLite, no changes needed
DB_CONNECTION=sqlite

# Session & Cache — use file driver (no external services)
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

# AI Configuration (optional)
AI_PROVIDER=none          # none, claude, openai, ollama
AI_API_KEY=               # API key for claude/openai
AI_MODEL=                 # e.g., claude-sonnet-4-5-20250514, gpt-4o, llama3
AI_BASE_URL=              # Required for openai/ollama (e.g., http://192.168.1.100:11434)
```

---

## Docker Architecture

```
┌─────────────────────────────────────────┐
│  Docker Compose                         │
│                                         │
│  ┌───────────┐  ┌──────┐  ┌──────┐    │
│  │   nginx   │  │ php  │  │ node │    │
│  │  :80      │→ │ :9000│  │ :5173│    │
│  │  (proxy)  │  │ (fpm)│  │(vite)│    │
│  └───────────┘  └──────┘  └──────┘    │
│                     │                   │
│              database/database.sqlite   │
└─────────────────────────────────────────┘
```

| Service | Image | Purpose |
|---------|-------|---------|
| nginx | nginx:alpine | Reverse proxy, serves static files |
| php | php:8.4-fpm (custom) | Laravel application server |
| node | node:22-alpine | Vite asset compilation (build only, not needed at runtime) |

### Production Optimization

In production, the `node` service is only needed during deployment to build assets. You can stop it after the build:

```bash
docker compose exec node npm run build
docker compose stop node
```

---

## Make Commands

```bash
make up          # Start all containers
make down        # Stop all containers
make build       # Rebuild containers
make shell       # Open a bash shell in the PHP container
make migrate     # Run database migrations
make seed        # Run database seeders
make fresh       # Drop all tables, re-migrate, and re-seed
make tinker      # Open Laravel Tinker REPL
make npm-install # Install npm dependencies
make vite-build  # Build frontend assets
```

---

## Backup & Restore

FinanzPilot uses a single SQLite file. Backup is a file copy.

### Backup

```bash
# Copy the database file
cp database/database.sqlite /path/to/backup/finanzpilot-$(date +%Y%m%d).sqlite

# Or from outside the container
docker compose exec php cp database/database.sqlite /var/www/html/storage/app/backup-$(date +%Y%m%d).sqlite
```

### Restore

```bash
# Stop the app
docker compose down

# Replace the database
cp /path/to/backup/finanzpilot-20260403.sqlite database/database.sqlite

# Restart
docker compose up -d
```

### Automated Backup (Cron)

Add to the host's crontab (`crontab -e`):

```cron
# Daily backup at 2 AM
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

Add to your Claude Code MCP settings (`~/.claude/claude_desktop_config.json` or project `.mcp.json`):

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

The MCP server communicates over stdio and accesses the SQLite database directly at `../database/database.sqlite` relative to the `mcp-server/` directory.

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

## Updating

```bash
cd /path/to/financial-pilot

# Pull latest code
git pull

# Rebuild containers (if Dockerfiles changed)
docker compose build

# Install/update dependencies
docker compose exec php composer install --no-dev --optimize-autoloader
docker compose exec node npm install
docker compose exec node npm run build

# Run new migrations
docker compose exec php php artisan migrate --force

# Restart
docker compose restart
```

---

## Troubleshooting

### App shows blank page
```bash
# Check Laravel logs
docker compose exec php tail -50 storage/logs/laravel.log

# Ensure storage permissions
docker compose exec php chown -R www-data:www-data storage bootstrap/cache
```

### Database locked errors
The SQLite config uses WAL mode with a 5-second busy timeout. If you see lock errors:
```bash
# Check for stuck processes
docker compose exec php php artisan tinker --execute="DB::select('PRAGMA journal_mode')"
# Should return: wal
```

### Assets not loading
```bash
# Rebuild frontend
docker compose exec node npm run build

# Check the manifest exists
ls public/build/manifest.json
```

### Port conflict
If port 80 is already in use, change the nginx port mapping in `docker-compose.yml`:
```yaml
ports:
  - "8080:80"  # Change 80 to any available port
```

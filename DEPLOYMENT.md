# FinanzPilot — Deployment Guide

FinanzPilot runs as a single Docker container (nginx, PHP-FPM and the scheduler). Everything you need to keep lives in one folder, `./data`, next to the compose file.

## Prerequisites

- Docker with Docker Compose
- (Optional) A reverse proxy or tunnel for HTTPS (Caddy, Traefik, nginx, Cloudflare Tunnel, …)

---

## Quick Start (pre-built image)

```bash
git clone https://github.com/tschaefermedia/FinanzPilot.git
cd FinanzPilot

# The container runs as uid 33 (www-data) and needs to own the data folder
mkdir data && sudo chown 33:33 data

docker compose -f docker-compose.prod.yml up -d
```

Open `http://<your-server-ip>` and create your account on the setup screen.

On every start the container:
- generates an `APP_KEY` into `data/.env` (first start only, unless you set `APP_KEY` yourself)
- runs database migrations and seeds the default categories into an empty database
- starts the scheduler (recurring transactions, upload cleanup, daily backups)

### Updating

```bash
git pull   # picks up changes to the compose file
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

## Build from Source

`docker-compose.yml` builds the same image locally:

```bash
mkdir -p data && sudo chown 33:33 data
docker compose up -d --build
```

For development without Docker use `composer run dev`.

---

## The data folder

| Path                    | Content                                               |
| ----------------------- | ----------------------------------------------------- |
| `data/database.sqlite`  | All your data (plus `-wal`/`-shm` files while running) |
| `data/.env`             | Generated `APP_KEY`, optional overrides               |
| `data/backups/`         | Automatic daily backups                               |
| `data/storage/`         | Logs, sessions, cache, temporary uploads              |

> **Keep `data/.env` with your backups.** The `APP_KEY` in it decrypts the AI API key stored in the settings. Without it you only have to re-enter the API key; your financial data is not encrypted with it.

## HTTPS / Reverse Proxy

The container listens on port 8080 (published as port 80 by the compose files). Behind a TLS-terminating proxy or tunnel, point it at `http://<host>:80` and set the public URL:

```yaml
environment:
  - APP_URL=https://finanzen.example.com
  - SESSION_SECURE_COOKIE=true
```

All generated links and asset URLs use `https://` when `APP_URL` starts with `https://`.

## Environment Variables

Set them under `environment:` in the compose file or in `data/.env`. Environment variables take precedence over `data/.env`.

| Variable                | Default            | Description                                                 |
| ----------------------- | ------------------ | ----------------------------------------------------------- |
| `APP_URL`               | `http://localhost` | Public URL, see [HTTPS](#https--reverse-proxy)               |
| `APP_KEY`               | *(generated)*      | Encryption key, generated into `data/.env` if not set        |
| `APP_DEBUG`             | `false`            | Detailed error pages, never enable on a public instance      |
| `SESSION_LIFETIME`      | `120`              | Minutes until an inactive session expires ("Angemeldet bleiben" keeps you logged in) |
| `SESSION_SECURE_COOKIE` | *(unset)*          | Set `true` when the app is only reached via HTTPS            |
| `LOG_LEVEL`             | `error`            | `debug`, `info`, `warning`, `error`                          |

AI providers (Claude, OpenAI, Ollama) are configured on the **Einstellungen** page, not via environment variables.

---

## Account

The first visit shows a setup screen to create the single login account. To reset a forgotten password:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan user:reset-password
```

## Backup & Restore

A consistent copy of the database is written to `data/backups/` every day at 02:00 (UTC). The newest 14 copies are kept. To create one manually:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan backup:database
```

For off-site backups, copy the whole `data/` folder (or at least `data/backups/` and `data/.env`) with your usual backup tool.

### Restore

```bash
docker compose -f docker-compose.prod.yml down
cp data/backups/finanzpilot-2026-10-30-020000.sqlite data/database.sqlite
rm -f data/database.sqlite-wal data/database.sqlite-shm
docker compose -f docker-compose.prod.yml up -d
```

---

## Upgrading from the two-container setup (nginx + php)

Earlier versions kept the database in `./database.sqlite`, the generated `APP_KEY` inside the `app-data` Docker volume, and required a running nginx container. Run these steps in the folder that contains your `docker-compose.prod.yml`, **before** `git pull`:

```bash
# 1. Stop the old containers
docker compose -f docker-compose.prod.yml down

# 2. Move the database into the data folder (keep a copy until everything works)
cp database.sqlite database.sqlite.bak
mkdir data
mv database.sqlite data/database.sqlite

# 3. Carry over the APP_KEY from the old volume (skip if you set APP_KEY in the compose file;
#    then keep that APP_KEY in the new compose file instead). Find the volume with `docker volume ls`.
docker run --rm -v finanzpilot_app-data:/old alpine grep '^APP_KEY=' /old/.env > data/.env

# 4. Hand the folder to the container user
sudo chown -R 33:33 data

# 5. Update and start
git pull
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d --remove-orphans
```

Then:
- If you reach FinanzPilot via HTTPS, set `APP_URL=https://…` (see [HTTPS](#https--reverse-proxy)). The app no longer forces `https://` on its own.
- The app now requires a login. If you never changed the old default account, it has been removed and you get the setup screen.
- The container now listens on port 8080. The compose file still publishes it on host port 80, so reverse proxies pointing at port 80 keep working.
- Once everything works, remove the old volume (`docker volume rm finanzpilot_app-data`) and `database.sqlite.bak`.

---

## MCP Server

The MCP server is a separate Node.js process on the host that reads and writes the SQLite file directly. It needs write access to the `data/` folder.

```bash
cd mcp-server
npm install
```

Add it to your Claude Code / Claude Desktop MCP settings:

```json
{
  "mcpServers": {
    "finanzpilot": {
      "command": "node",
      "args": ["/path/to/FinanzPilot/mcp-server/index.js"],
      "env": { "FINANZPILOT_DB_PATH": "/path/to/FinanzPilot/data/database.sqlite" }
    }
  }
}
```

Without `FINANZPILOT_DB_PATH` it uses `database/database.sqlite` (local development).

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

## Troubleshooting

### Container stops right after start with "/data is not writable"
```bash
sudo chown -R 33:33 data
```

### Logs
```bash
docker compose -f docker-compose.prod.yml logs app      # web server, startup
tail -50 data/storage/logs/laravel.log                  # application errors
tail -50 data/storage/logs/scheduler.log                # scheduled tasks
```

### Page loads without styles behind HTTPS
`APP_URL` is still `http://…`. Set it to your `https://` URL and restart.

### Port conflict
Change the published port in the compose file, e.g. `"8081:8080"`.

# FinanzPilot — Web App PRD

**Version:** 2.0 (Web App Migration)
**Date:** April 2026
**Author:** Tobias Schäfer / BuildIT Consulting
**Status:** Draft

---

## 1. Overview & Vision

FinanzPilot is a self-hosted, single-user personal finance web application. It replaces the current Google Sheets-based finance tracking with a proper web app running on a Docker-based home server (Proxmox).

The app gives full control over financial data without relying on third-party SaaS products, while adding intelligent categorization, AI-powered insights, and programmatic access via MCP.

---

## 2. Baseline: Features Carried Over from Google Sheet

These are the existing capabilities from the current spreadsheet setup that must be preserved:

- **Transaction tracking** — Manual entry of income and expenses with date, amount, description, category, and notes
- **Category-based organization** — Hierarchical categories (e.g., "Food → Groceries", "Housing → Rent") with income/expense/transfer types
- **Monthly overview** — Summary of income vs. expenses per month with category breakdowns
- **Balance tracking** — Running balance calculation across months
- **Recurring transactions** — Tracking of subscriptions, rent, salary, and other repeating items
- **Debt/loan tracking** — Overview of bank loans (principal, interest rate, term) and informal debts (who owes whom)
- **Basic visualizations** — Monthly trends, category distribution, income vs. expense comparison

---

## 3. New Features

### 3.1 Excel Export (per Month)

**Purpose:** Allow exporting any single month (or date range) as a standalone `.xlsx` file for offline use, sharing with a tax advisor, or archival.

**Behavior:**
- User selects a month (or custom date range) from the UI
- System generates an `.xlsx` file containing: a transaction sheet (all transactions in the period with full details), a summary sheet (totals by category, income/expense breakdown, balance), and optionally a chart sheet with embedded visualizations
- File downloads directly in the browser
- Export must preserve German number formatting (comma as decimal separator) and EUR currency formatting
- Column headers in German

**Edge cases:**
- Months with zero transactions should still export with headers and a "Keine Buchungen" note
- Recurring templates that fall within the period should be included (whether auto-generated or pending)

### 3.2 Raw Data Import with Categorization Workflow

**Purpose:** Import rough/unstructured financial data from external sources (bank CSVs, PayPal exports, manual CSV dumps) and guide the user through categorizing each transaction.

**Behavior — Import Pipeline:**
1. User uploads a file (CSV, MT940, or a generic delimited file)
2. System auto-detects the source format (Sparkasse CSV-CAMT, PayPal CSV, generic CSV) based on headers, encoding, and field structure
3. System normalizes all entries into the canonical transaction format (date, amount, description, counterparty, reference)
4. Encoding is handled automatically (Sparkasse: ISO-8859-1 → UTF-8; PayPal: UTF-8)
5. Duplicate detection via SHA-256 hash of date + amount + reference — duplicates are flagged, not silently skipped

**Behavior — Categorization Workflow:**
1. After import, uncategorized transactions appear in a **review queue**
2. The rule engine attempts auto-categorization based on stored rules (pattern matching on description/counterparty → category)
3. Transactions with high-confidence matches are pre-categorized but still visible for review
4. Transactions with low confidence or no match are highlighted for manual categorization
5. Every manual categorization decision is offered as a new rule candidate ("Always categorize 'REWE' as Groceries?")
6. The rule engine is self-learning: confidence scores increase with repeated confirmations
7. Bulk actions available: select multiple transactions → assign same category
8. AI-assisted suggestions as a fallback: if no rule matches, the system can call Claude to suggest a category based on the transaction description (anonymized — no counterparty names sent)

**Supported formats (MVP):**
- Sparkasse CSV-CAMT (semicolon-delimited, ISO-8859-1, German headers)
- PayPal CSV (comma-delimited, UTF-8, EUR only — multi-currency out of scope)
- Generic CSV (user maps columns manually on first import)

### 3.3 MCP / AI Access to Financial Data

**Purpose:** Expose FinanzPilot data via an MCP (Model Context Protocol) server so that Claude (via Claude Code, Claude Desktop, or the API) can read, query, and analyze financial data conversationally.

**MCP Server — Resources (read-only):**
- `finanzpilot://transactions` — List/filter transactions by date range, category, source, amount range
- `finanzpilot://categories` — Full category tree with monthly totals
- `finanzpilot://summary/{month}` — Monthly summary (income, expenses, savings rate, top categories)
- `finanzpilot://loans` — Active loans with remaining balance and next payment
- `finanzpilot://recurring` — All recurring templates with status
- `finanzpilot://balance` — Current balance and trend

**MCP Server — Tools (write operations, with confirmation):**
- `add_transaction` — Create a manual transaction
- `categorize_transaction` — Assign or change a category
- `add_rule` — Create a new categorization rule

**AI Integration (in-app):**
- FinancialSnapshot service collects and **anonymizes** data before sending to Claude API
- All amounts normalized to percentages/ratios (income indexed to 100)
- No counterparty names, no absolute amounts, no personal identifiers
- Cached per snapshot hash — no redundant API calls
- AI suggestions displayed as a dashboard widget with actionable insights
- System prompt engineered for a financial advisor persona giving specific, actionable advice — not generic tips

**Security considerations:**
- MCP server binds to localhost only (or Tailscale/WireGuard network)
- Bearer token authentication for MCP endpoints
- Write operations require explicit confirmation (tool returns a preview, user confirms)
- AI anonymization layer is mandatory — raw data never leaves the server

---

## 4. Tech Stack

| Component | Technology | Rationale |
|-----------|-----------|-----------|
| Backend | Laravel 11 | Familiar stack, rapid development, strong ecosystem |
| Frontend | Vue 3 + Inertia.js | Monolithic SPA feel without API complexity |
| Database | SQLite | Single-user, zero config, file-based backup |
| Charts | Chart.js or ApexCharts | Lightweight, good Vue integration |
| Deployment | Docker Compose | Nginx + Laravel + Node (asset build) on Proxmox |
| MCP Server | Node.js or PHP | Runs alongside the app, connects to SQLite |
| Excel Export | Laravel Excel (Maatwebsite) or PhpSpreadsheet | Native .xlsx generation |

---

## 5. Data Model (Core Entities)

**Transactions** — The canonical record. Fields: date, signed amount (negative = expense), description, counterparty, category (FK), source (sparkasse/paypal/manual/recurring), reference, SHA-256 hash for dedup, notes.

**Categories** — Hierarchical. Fields: name, type (income/expense/transfer), icon, parent category (nullable), optional monthly budget target.

**Category Rules** — Self-learning engine. Fields: pattern (string or regex), target category (FK), priority, confidence score, hit count.

**Loans** — Bank and informal. Fields: name, type, principal, interest rate, start date, term, payment day, direction (owed by me / owed to me), notes.

**Loan Payments** — Linked to transactions when auto-matched. Fields: loan (FK), transaction (FK, nullable), date, amount, type (scheduled/extra/manual).

**Recurring Templates** — Fields: description, amount, category (FK), frequency (monthly/weekly/quarterly/yearly), next due date, active flag, auto-generate flag.

**Import Batches** — Audit trail. Fields: filename, source type, upload date, row count, status (pending/reviewed/committed).

---

## 6. Core Screens

| Screen | Purpose |
|--------|---------|
| **Dashboard** | Financial health at a glance — income vs. expenses bar chart, category donut, balance trend line, debt summary card, AI insights card |
| **Transactions** | Filterable table with inline category editing, bulk actions, search by description/counterparty/amount range |
| **Import** | File upload → source detection → preview table with auto-categorization confidence indicators, duplicate flags, batch confirm/reject |
| **Review Queue** | Uncategorized transactions from imports — card-by-card or table view, rule suggestion on each manual categorization |
| **Categories** | Tree view with CRUD, rule management per category, confidence scores |
| **Loans** | Active loans, amortization schedules, payoff projections, informal debt ledger |
| **Recurring** | Template list, frequency settings, next due dates, active/inactive toggle |
| **Export** | Month/range selector → preview → download .xlsx |
| **Settings** | AI API key config, MCP server toggle, backup schedule, import format preferences, default category seeding |

---

## 7. Design Principles

- **Minimal and clean** — No visual clutter, dashboard-first navigation
- **Non-destructive** — Transactions can always be re-categorized, loans unlinked, imports rolled back
- **Import-driven workflow** — The import → review → categorize loop is the primary interaction pattern
- **Guided, not automated** — Auto-categorization proposes, user confirms. No silent data changes.
- **Self-hosted, zero external dependencies** — Works fully offline except for optional AI suggestions
- **Single-file backup** — SQLite DB + Docker volume = copy one file to back up everything

---

## 8. Implementation Phases

**Phase 1 — Foundation**
- Laravel + Vue + Inertia scaffold in Docker Compose
- SQLite database with migrations for all core entities
- Transaction CRUD (manual input)
- Category management with hierarchy
- Basic dashboard with 3 charts (income vs. expenses, category breakdown, balance trend)

**Phase 2 — Import & Categorization**
- Sparkasse CSV-CAMT parser with encoding handling
- Generic CSV parser with column mapping UI
- Import pipeline: upload → normalize → dedup → preview → commit
- Rule-based auto-categorization engine
- Self-learning rules from manual categorization
- Review queue UI

**Phase 3 — Recurring & Loans**
- Recurring transaction templates with auto-generation scheduler
- Bank loan module: amortization calculation, payment tracking, payoff projections
- Informal loan ledger with directional tracking
- Loan payment auto-matching from imported transactions

**Phase 4 — Export**
- Monthly .xlsx export with transaction detail + summary sheets
- German number/currency formatting
- Custom date range support
- Batch export (multiple months as separate sheets in one file)

**Phase 5 — AI & MCP**
- FinancialSnapshot anonymization service
- Claude API integration with response caching
- AI insights dashboard widget
- MCP server with read resources and write tools
- Bearer token auth for MCP
- PayPal CSV parser

---

## 9. Out of Scope (for now)

- Multi-user / authentication (single-user personal tool)
- Multi-currency support (EUR only)
- Bank API integrations (FinTS/PSD2) — CSV import is sufficient
- Mobile-native app (responsive web is enough)
- Real-time bank sync
- Tax filing integration / DATEV export (may be added later)

---

## 10. Success Criteria

- All data from the current Google Sheet can be imported into the app
- Monthly import-categorize-review cycle takes less than 15 minutes
- Excel export produces a file that a German tax advisor can work with directly
- MCP server allows Claude to answer questions like "How much did I spend on groceries in March?" from the terminal
- Full backup/restore is a single file copy
- App runs reliably on Proxmox Docker with no maintenance

---

## 11. Open Questions

1. **MT940 vs. CSV-CAMT** — Which format does your Sparkasse actually export? (We discussed this in Feb — worth confirming before building the parser)
2. **Budget tracking** — The category model has a `budget_monthly` field. Should Phase 1 include budget vs. actual comparisons, or defer to a later phase?
3. **MCP write safety** — Should write operations via MCP require a confirmation step in the app UI (push notification / pending queue), or is CLI confirmation in Claude Code sufficient?
4. **Generic CSV mapper** — How flexible does this need to be? Should the app remember column mappings per source name for repeat imports?
5. **AI provider flexibility** — Should the AI integration support only Claude, or also allow local models (Ollama) as a fallback for fully offline operation?

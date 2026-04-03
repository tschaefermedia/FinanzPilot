import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import Database from 'better-sqlite3';
import { z } from 'zod';
import path from 'path';
import { fileURLToPath } from 'url';
import crypto from 'crypto';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DB_PATH = path.resolve(__dirname, '..', 'database', 'database.sqlite');

let db;
function getDb() {
  if (!db) {
    db = new Database(DB_PATH, { readonly: false });
    db.pragma('journal_mode = WAL');
    db.pragma('foreign_keys = ON');
  }
  return db;
}

const server = new McpServer({
  name: 'finanzpilot',
  version: '1.0.0',
});

// --- RESOURCES (read-only) ---

server.resource('transactions', 'finanzpilot://transactions', ({ uri }) => {
  const rows = getDb().prepare(`
    SELECT t.id, t.date, t.amount, t.description, t.counterparty,
           c.name as category, t.source, t.reference
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.deleted_at IS NULL
    ORDER BY t.date DESC
    LIMIT 100
  `).all();
  return { contents: [{ uri, text: JSON.stringify(rows, null, 2), mimeType: 'application/json' }] };
});

server.resource('categories', 'finanzpilot://categories', ({ uri }) => {
  const rows = getDb().prepare(`
    SELECT c.id, c.name, c.type, c.icon, p.name as parent_name,
           COUNT(t.id) as transaction_count,
           COALESCE(SUM(ABS(t.amount)), 0) as total_amount
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    LEFT JOIN transactions t ON t.category_id = c.id AND t.deleted_at IS NULL
    WHERE c.deleted_at IS NULL
    GROUP BY c.id
    ORDER BY c.sort_order, c.name
  `).all();
  return { contents: [{ uri, text: JSON.stringify(rows, null, 2), mimeType: 'application/json' }] };
});

server.resource('summary-current', 'finanzpilot://summary/current', ({ uri }) => {
  const month = new Date().toISOString().slice(0, 7);
  const row = getDb().prepare(`
    SELECT
      SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
      SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses,
      COUNT(*) as transaction_count
    FROM transactions
    WHERE strftime('%Y-%m', date) = ? AND deleted_at IS NULL
  `).get(month);
  const income = row?.income ?? 0;
  const expenses = row?.expenses ?? 0;
  const balance = income - expenses;
  const savingsRate = income > 0 ? ((balance / income) * 100).toFixed(1) : '0.0';
  const summary = { month, income, expenses, balance, savingsRate: parseFloat(savingsRate), transactionCount: row?.transaction_count ?? 0 };
  return { contents: [{ uri, text: JSON.stringify(summary, null, 2), mimeType: 'application/json' }] };
});

server.resource('loans', 'finanzpilot://loans', ({ uri }) => {
  const rows = getDb().prepare(`
    SELECT l.id, l.name, l.type, l.principal, l.interest_rate, l.start_date,
           l.term_months, l.direction, l.notes,
           COALESCE(SUM(lp.amount), 0) as total_paid
    FROM loans l
    LEFT JOIN loan_payments lp ON lp.loan_id = l.id
    WHERE l.deleted_at IS NULL
    GROUP BY l.id
  `).all();
  return { contents: [{ uri, text: JSON.stringify(rows, null, 2), mimeType: 'application/json' }] };
});

server.resource('recurring', 'finanzpilot://recurring', ({ uri }) => {
  const rows = getDb().prepare(`
    SELECT rt.id, rt.description, rt.amount, c.name as category,
           rt.frequency, rt.next_due_date, rt.is_active, rt.auto_generate
    FROM recurring_templates rt
    LEFT JOIN categories c ON rt.category_id = c.id
    ORDER BY rt.next_due_date
  `).all();
  return { contents: [{ uri, text: JSON.stringify(rows, null, 2), mimeType: 'application/json' }] };
});

server.resource('balance', 'finanzpilot://balance', ({ uri }) => {
  const row = getDb().prepare(`
    SELECT COALESCE(SUM(amount), 0) as balance FROM transactions WHERE deleted_at IS NULL
  `).get();
  const trend = getDb().prepare(`
    SELECT strftime('%Y-%m', date) as month, SUM(amount) as net
    FROM transactions WHERE deleted_at IS NULL
    GROUP BY strftime('%Y-%m', date)
    ORDER BY month DESC LIMIT 6
  `).all();
  return { contents: [{ uri, text: JSON.stringify({ balance: row.balance, trend }, null, 2), mimeType: 'application/json' }] };
});

// --- TOOLS (write operations) ---

server.tool(
  'query_transactions',
  'Search and filter transactions by date range, category, amount, or description',
  {
    date_from: z.string().optional().describe('Start date (YYYY-MM-DD)'),
    date_to: z.string().optional().describe('End date (YYYY-MM-DD)'),
    category: z.string().optional().describe('Category name to filter by'),
    min_amount: z.number().optional().describe('Minimum amount'),
    max_amount: z.number().optional().describe('Maximum amount'),
    search: z.string().optional().describe('Search in description or counterparty'),
    limit: z.number().optional().default(50).describe('Max results (default 50)'),
  },
  async ({ date_from, date_to, category, min_amount, max_amount, search, limit }) => {
    let sql = `
      SELECT t.id, t.date, t.amount, t.description, t.counterparty, c.name as category, t.source
      FROM transactions t
      LEFT JOIN categories c ON t.category_id = c.id
      WHERE t.deleted_at IS NULL
    `;
    const params = [];

    if (date_from) { sql += ' AND t.date >= ?'; params.push(date_from); }
    if (date_to) { sql += ' AND t.date <= ?'; params.push(date_to); }
    if (category) { sql += ' AND c.name LIKE ?'; params.push(`%${category}%`); }
    if (min_amount !== undefined) { sql += ' AND t.amount >= ?'; params.push(min_amount); }
    if (max_amount !== undefined) { sql += ' AND t.amount <= ?'; params.push(max_amount); }
    if (search) { sql += ' AND (t.description LIKE ? OR t.counterparty LIKE ?)'; params.push(`%${search}%`, `%${search}%`); }

    sql += ' ORDER BY t.date DESC LIMIT ?';
    params.push(limit ?? 50);

    const rows = getDb().prepare(sql).all(...params);
    return { content: [{ type: 'text', text: JSON.stringify(rows, null, 2) }] };
  }
);

server.tool(
  'add_transaction',
  'Add a new manual transaction',
  {
    date: z.string().describe('Transaction date (YYYY-MM-DD)'),
    amount: z.number().describe('Amount (positive=income, negative=expense)'),
    description: z.string().describe('Transaction description'),
    counterparty: z.string().optional().describe('Counterparty name'),
    category: z.string().optional().describe('Category name'),
    notes: z.string().optional().describe('Additional notes'),
  },
  async ({ date, amount, description, counterparty, category, notes }) => {
    let categoryId = null;
    if (category) {
      const cat = getDb().prepare('SELECT id FROM categories WHERE name LIKE ? AND deleted_at IS NULL').get(`%${category}%`);
      categoryId = cat?.id ?? null;
    }

    const now = new Date().toISOString().replace('T', ' ').slice(0, 19);
    const reference = description.substring(0, 50);
    const hash = crypto.createHash('sha256').update(date + '|' + amount.toFixed(2) + '|' + reference).digest('hex');

    const result = getDb().prepare(`
      INSERT INTO transactions (date, amount, description, counterparty, category_id, source, hash, notes, created_at, updated_at)
      VALUES (?, ?, ?, ?, ?, 'manual', ?, ?, ?, ?)
    `).run(date, amount, description, counterparty ?? null, categoryId, hash, notes ?? null, now, now);

    return { content: [{ type: 'text', text: `Buchung erstellt (ID: ${result.lastInsertRowid}): ${description} — ${amount}€ am ${date}` }] };
  }
);

server.tool(
  'categorize_transaction',
  'Assign or change a category for a transaction',
  {
    transaction_id: z.number().describe('Transaction ID'),
    category: z.string().describe('Category name'),
  },
  async ({ transaction_id, category }) => {
    const cat = getDb().prepare('SELECT id, name FROM categories WHERE name LIKE ? AND deleted_at IS NULL').get(`%${category}%`);
    if (!cat) {
      return { content: [{ type: 'text', text: `Kategorie "${category}" nicht gefunden.` }] };
    }

    const now = new Date().toISOString().replace('T', ' ').slice(0, 19);
    getDb().prepare('UPDATE transactions SET category_id = ?, updated_at = ? WHERE id = ?').run(cat.id, now, transaction_id);

    return { content: [{ type: 'text', text: `Buchung ${transaction_id} → Kategorie "${cat.name}" zugewiesen.` }] };
  }
);

server.tool(
  'add_rule',
  'Create a new categorization rule',
  {
    pattern: z.string().describe('Pattern to match (substring of description/counterparty)'),
    category: z.string().describe('Target category name'),
    is_regex: z.boolean().optional().default(false).describe('Whether pattern is a regex'),
  },
  async ({ pattern, category, is_regex }) => {
    const cat = getDb().prepare('SELECT id, name FROM categories WHERE name LIKE ? AND deleted_at IS NULL').get(`%${category}%`);
    if (!cat) {
      return { content: [{ type: 'text', text: `Kategorie "${category}" nicht gefunden.` }] };
    }

    const now = new Date().toISOString().replace('T', ' ').slice(0, 19);
    const result = getDb().prepare(`
      INSERT INTO category_rules (pattern, is_regex, target_category_id, priority, confidence, hit_count, created_at, updated_at)
      VALUES (?, ?, ?, 0, 0.5, 0, ?, ?)
    `).run(pattern, is_regex ? 1 : 0, cat.id, now, now);

    return { content: [{ type: 'text', text: `Regel erstellt (ID: ${result.lastInsertRowid}): "${pattern}" → "${cat.name}"` }] };
  }
);

// --- START ---
async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error('FinanzPilot MCP server running on stdio');
}

main().catch(console.error);

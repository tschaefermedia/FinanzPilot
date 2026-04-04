# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability, please report it responsibly:

1. **Do not** open a public issue
2. Email security concerns to: me@tobias-schaefer.com
3. Include a description of the vulnerability and steps to reproduce
4. Allow reasonable time for a fix before public disclosure

We take security seriously and will respond within 48 hours.

## Security Considerations

FinanzPilot is designed as a self-hosted, single-user application:

- **No authentication by default** — intended to run behind a reverse proxy or VPN (e.g., Cloudflare Tunnel, Tailscale)
- **SQLite database** — file-level access control, no network-exposed database
- **AI anonymization** — financial data is normalized to percentages before sending to AI providers
- **MCP server** — binds to stdio only, no network exposure

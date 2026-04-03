# Contributing to FinanzPilot

Thank you for your interest in contributing!

## Getting Started

1. Fork the repository
2. Clone your fork: `git clone https://github.com/YOUR_USERNAME/financial-pilot.git`
3. Create a feature branch: `git checkout -b feature/your-feature`
4. Make your changes
5. Run the linter: `vendor/bin/pint`
6. Build frontend: `npm run build`
7. Commit and push
8. Open a Pull Request

## Development Setup

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run dev        # Vite dev server
php artisan serve  # Laravel dev server
```

## Guidelines

- Write clean, readable code
- Follow Laravel and Vue.js conventions
- German UI — all user-facing strings in German
- Keep the app self-contained (SQLite, no external services required)
- Test your changes before submitting a PR

## Reporting Issues

- Use GitHub Issues
- Include steps to reproduce
- Include error messages and logs if applicable

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

# Genesis Inventory App

A small plain-PHP inventory application for the DevOps portfolio project. It uses MariaDB via PDO, provides full CRUD for stock items, and exposes a `/health` endpoint for platform checks.

## Features

- Create, list, update, and delete inventory items
- MariaDB connection via PDO
- Session-based flash messages and CSRF protection
- `/health` endpoint with database check
- No framework dependency

## Files

- `public/index.php` - Front controller, routes, CRUD flow, HTML output
- `public/assets/style.css` - UI styling
- `src/bootstrap.php` - Config bootstrap and helpers
- `src/env.php` - Minimal `.env` loader
- `src/db.php` - PDO factory
- `src/InventoryRepository.php` - Database access layer
- `schema.sql` - Database schema and sample data
- `.env.example` - Environment template
- `router.php` - Router for PHP's built-in server

## Local start

1. Install PHP 8.2+ with `pdo_mysql`.
2. Install MariaDB and create a database user.
3. Copy the env template:
   ```bash
   cp .env.example .env
   ```
4. Edit `.env` with the correct database credentials.
5. Import the schema:
   ```bash
   mysql -u genesis -p < schema.sql
   ```
6. Start the local dev server from this directory:
   ```bash
   php -S 127.0.0.1:8080 router.php
   ```
7. Open `http://127.0.0.1:8080`.
8. Check health with `http://127.0.0.1:8080/health`.

---
name: GSB SINIYAT project stack
description: Key technical decisions for the GSB SINIYAT school management system
---

## Stack decisions

- **Database**: PostgreSQL via Replit built-in (not MySQL as originally specified — MySQL is unavailable on Replit)
- **PHP version**: 8.2
- **PDF**: Dompdf (dompdf/dompdf ^3.1)
- **Excel export**: PhpSpreadsheet (phpoffice/phpspreadsheet ^5.9)
- **Server**: PHP built-in server via `php -S 0.0.0.0:5000 router.php`
- **Session name**: `SINIYAT_SESSION`

**Why:** Replit provides PostgreSQL as the built-in database. PDO works identically for both; SQL syntax differences are minor (SERIAL instead of AUTO_INCREMENT, ILIKE instead of LIKE for case-insensitive search).

**How to apply:** Always use `pgsql:` DSN in `config/database.php`. Use `ILIKE` for case-insensitive text search. Use `RETURNING id` instead of `lastInsertId()` for new row IDs.

## Default admin credentials
- Login: `admin`
- Password: `Admin@2026!` (bcrypt hash stored in DB)

## Receipt numbering
- `seq_numero_recu` PostgreSQL sequence — never reset, globally unique across all years.
- `seq_matricule` PostgreSQL sequence — used to generate student IDs per year.

## Key incomplete features (follow-up tasks proposed)
- Optional fees (frais annexes) UI — DB tables exist, no UI yet
- CM2 exam candidate list with unpaid alert
- Production deployment

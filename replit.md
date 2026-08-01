# Groupe Scolaire Bilingue SINIYAT — Système de Gestion Scolaire

## Overview
Full-stack school management system (PHP 8.2 + PostgreSQL) for GSB SINIYAT.
Handles student enrollment, fee collection, receipts, and academic year management.

## Stack
- **Backend**: PHP 8.2 (PDO, prepared statements only)
- **Frontend**: HTML5, Bootstrap 5, Vanilla JS
- **Database**: PostgreSQL (Replit built-in)
- **PDF**: Dompdf (via Composer)
- **Excel export**: PhpSpreadsheet (via Composer)
- **PWA**: manifest.json + service worker

## Project Structure
```
/
├── index.php             # Entry point (redirects)
├── login.php             # Login page
├── logout.php
├── change_password.php
├── router.php            # PHP built-in server router
├── manifest.json         # PWA manifest
├── sw.js                 # Service worker
├── offline.html          # Offline fallback page
├── config/
│   ├── database.php      # PDO connection
│   └── config.php        # Global constants
├── includes/
│   ├── session.php       # Secure session management
│   ├── csrf.php          # CSRF protection
│   ├── functions.php     # Core utility functions
│   ├── header.php        # Common HTML header + nav
│   └── footer.php        # Common HTML footer
├── admin/
│   ├── index.php         # Admin dashboard
│   ├── users.php         # User management
│   ├── classes.php       # Level/class management
│   ├── fees.php          # Fee structure editor
│   ├── academic_years.php # Year management + mass promotion
│   └── audit_log.php     # Audit log viewer
├── secretary/
│   ├── index.php         # Secretary dashboard
│   ├── students.php      # Student enrollment form
│   ├── student_view.php  # Student profile + financial status
│   ├── search.php        # Student search
│   └── payments.php      # Payment recording
├── api/
│   ├── students.php      # Student search API
│   ├── lang.php          # Language switch API
│   ├── ping.php          # Session keep-alive
│   └── export.php        # Excel/CSV export
├── pdf/
│   └── receipt.php       # PDF receipt generator (Dompdf)
├── assets/
│   ├── css/style.css
│   ├── js/app.js, lang.js, idle-timer.js
│   └── img/logo.png
├── lang/
│   ├── fr.json
│   └── en.json
└── vendor/               # Composer dependencies
```

## Run Command
```bash
php -S 0.0.0.0:5000 router.php
```

## Default Admin Credentials
- **Login**: `admin`
- **Password**: `password` (change on first login!)

## Key Features
1. **Two roles**: Admin (full access) + Secrétaire/Caissière (enrollment + payments)
2. **Student management**: Full enrollment form with document checklist
3. **Financial management**: Configurable fee structure per class/year, installments
4. **Discounts**: 2% for full payment, 2% per additional sibling
5. **PDF receipts**: Auto-generated with unique sequential numbers (never reset)
6. **Academic years**: New year workflow copies fee structure, archives previous year
7. **Mass promotion**: Move students from one class/year to the next
8. **PWA**: Installable, offline-capable (read-only)
9. **Multilingual**: FR/EN without page reload
10. **Audit log**: All sensitive actions logged
11. **Auto-logout**: 10 minutes of inactivity

## Security
- PDO prepared statements everywhere
- Passwords hashed (bcrypt cost 12)
- CSRF tokens on all forms
- Session regeneration on login
- HttpOnly session cookies
- XSS escaping (htmlspecialchars)
- 10-min inactivity auto-logout

## User Preferences
- Use icons (Bootstrap Icons), never emojis
- Logo displayed in circular frame
- Blue navy (#0d1b4b) primary color
- Dates in DD/MM/YYYY format
- Amounts in FCFA

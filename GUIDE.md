# Kamel Ventures — Deployment & Operations Guide

## What this is

A self-contained PHP/MySQL signup system hosted on Hostinger. Visitors fill out the landing page form, get a confirmation email, and their info lands in the admin panel.

**Files at a glance:**

| File | Purpose |
|---|---|
| `index.html` | Public landing page with signup form |
| `submit.php` | Form submission endpoint (JSON API) |
| `admin.php` | Password-protected admin panel |
| `config.php` | DB credentials + email settings (**not in git**) |
| `schema.sql` | MySQL table definition |
| `.htaccess` | Directory security + HTTPS redirect |

---

## First-time deployment (Hostinger)

### 1. Create the database

In Hostinger → **Databases → MySQL Databases**, create a database and note the:
- Host (usually `localhost`)
- Database name
- Username
- Password

### 2. Run the schema

Open **phpMyAdmin**, select your database, click the **SQL** tab, paste the contents of `schema.sql`, and run it.

> If the table already exists from a previous deploy, run only the `ALTER TABLE` line at the bottom of `schema.sql` instead.

### 3. Configure `config.php`

Upload `config.php` to the server (it is gitignored — you must do this manually each time):

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

define('MAIL_FROM',      'hello@kamelventures.org');
define('MAIL_FROM_NAME', 'Kamel Ventures');

define('ADMIN_PASSWORD_HASH', '');   // leave empty on first deploy
```

### 4. Upload files

Upload everything except `.git/` and `.DS_Store` to `public_html/` (or your domain root):

```
.htaccess
admin.php
camel.png
config.php       ← manual upload only (not in git)
index.html
submit.php
schema.sql       ← optional, for reference
```

### 5. Set your admin password

SSH into the server (or use Hostinger terminal) and run:

```bash
php -r "echo password_hash('your-chosen-password', PASSWORD_DEFAULT);"
```

Copy the output hash and paste it into `config.php`:

```php
define('ADMIN_PASSWORD_HASH', '$2y$10$...');
```

Re-upload `config.php`. You can now log into `/admin.php`.

### 6. Enable HTTPS redirect (after SSL is active)

Uncomment the three `RewriteEngine` lines in `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## Admin panel

Visit `https://kamelventures.org/admin.php`.

**What you can do:**
- View all signups with name, email, role, investment amount, and signup date
- See stat cards: total signups, breakdown by role, and total pledged (USD)
- Delete individual signups
- Export the full list as a CSV (`First Name, Last Name, Email, Role, Amount (USD), Signed Up`)

---

## Signup form fields

| Field | Required | Notes |
|---|---|---|
| First name | Yes | |
| Last name | Yes | |
| Email | Yes | Duplicate emails are rejected with a friendly message |
| Role | No | investor / founder / mentor / other |
| Amount (USD) | No | Integer ≥ 1; stored as `NULL` if blank |
| Checkbox | Yes | Consent to be contacted |

On success the form shows a confirmation message and fires a confirmation email from `hello@kamelventures.org`.

---

## Adding the `amount` column to an existing database

If you deployed before the amount field was added, run this once in phpMyAdmin:

```sql
ALTER TABLE signups ADD COLUMN amount INT UNSIGNED DEFAULT NULL AFTER role;
```

---

## Security notes

- `config.php` is blocked by `.htaccess` from direct browser access and is excluded from git via `.gitignore`.
- All form inputs are sanitized with `htmlspecialchars` / `strip_tags` and inserted via PDO prepared statements (no SQL injection risk).
- The admin panel is protected by a `password_verify` check against a bcrypt hash — never store the plaintext password.
- Directory listing is disabled via `Options -Indexes`.

---

## Updating the site

```bash
# on your local machine
git add <files>
git commit -m "your message"

# then either:
# a) push to GitHub and pull on the server, or
# b) manually re-upload changed files via Hostinger File Manager / FTP
```

`config.php` is always a manual upload — never commit it.

# Security Remediation — bactiveph_com

**Date:** 2026-05-31 · Status: partially remediated; **manual credential rotation still required.**

During the early build, credentials were hard-coded into deployment scripts and committed to git, and `wp-config.php` was tracked. This documents what was found, what's been fixed in code, and what **you still must do**.

---

## What was found
- The production **FTP password** was hard-coded in ~20 scripts.
- The **cPanel API token** was hard-coded in `run_step3_fix.py`.
- The **WordPress DB password** was hard-coded in `generate_wp_config.py` (and lives in the committed `wp-config.php`).
- The **staging DB password** was hard-coded in `cpanel_provision.py`.
- `recover.php` is a backdoor that creates or resets an admin with a plaintext password.
- `wp-config.php` (DB credentials + secret salts) and several one-off server PHP scripts are **tracked in git**.

## What has been fixed (in code, locally)
- Added `env_loader.py` — a no-dependency loader that reads `.env` into the environment.
- Scrubbed 22 Python scripts to read secrets from `.env` (`os.environ[...]`) instead of literals. No secret literals remain in any `.py`.
- Added `.gitignore` rules for `wp-config.php`, `*.sql`, and the one-off server scripts.
- Extended `.env.example` with the keys the scripts now expect.

> Note: scrubbing the working files does **not** remove secrets from **git history**. The only true fix is rotation (below).

---

## What YOU must still do (in order)

### 1. Rotate the FTP password — CRITICAL, do first
The agent could not change it via API, so it is unchanged — meaning the old password is still **live** and still in git history.
- cPanel → **FTP Accounts** → `bactive@bactiveph.com` → **Change Password** → set a new strong password.
- Put the new value in `.env` as `FTP_PASSWORD=...` (never in a script).

### 2. Rotate the other exposed credentials
- **cPanel API token:** cPanel → *Manage API Tokens* → revoke the old token, create a new one → `.env` as `CPANEL_API_TOKEN=...`.
- **cPanel account password:** change it in your Namecheap/cPanel login.
- **WordPress DB password:** cPanel → *MySQL Databases* → change the DB user's password → update `wp-config.php` **on the server** to match (and the staging `wp-config.php`). Put it in `.env` as `WP_DB_PASSWORD=...`.
- **Staging DB password** (`STAGING_DB_PASSWORD`) and the **`bactive_support` admin password** (the agent already rotated this one — confirm it's stored safely).

### 3. Stop tracking secret files in git
```bash
git rm --cached wp-config.php recover.php extract.php \
  test_remote.php test_wp_cli.php test_wpcli.php \
  wp_audit.php wp_backup.php wp_check_plugins.php wp_clone.php \
  wp_content_setup.php wp_ia_setup.php wp_install_plugins.php wp_setup.php \
  wordpress/research.php
git commit -m "security: stop tracking wp-config and one-off server scripts; load creds from .env"
```

### 4. Delete the leftover backdoor/one-off scripts (local + confirm server)
`recover.php` is **still present locally** even though the agent reported deleting it — delete the local copy, and **verify it (and `research.php`, `wp_*.php`, `test_*.php`) are gone from the live server's web root.**
```bash
rm -f recover.php research.php extract.php wp_*.php test_*.php step*.php cache_fix.php
```

### 5. (Optional) Purge secrets from git history
Because the FTP/DB passwords were committed, they remain in history. After rotating (steps 1–2) the old values are useless, so this is optional. If you want them gone: use `git filter-repo` (or BFG Repo-Cleaner) to strip `wp-config.php` and the secret strings, then force-push. Rotation makes this non-urgent.

---

## Going forward
- All secrets live in `.env` only (git-ignored). Scripts read them via `import os, env_loader`.
- Never commit `wp-config.php` or any file containing a credential.
- Never leave an executable PHP script (admin-creator, file-mover, etc.) on the live server after use.

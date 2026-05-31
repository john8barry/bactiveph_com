# Security Remediation — bactiveph.com

**Status (2026-05-31): FTP password ROTATED ✅. Remaining: stop tracking secret files, delete backdoor scripts from the server, rotate the DB/cPanel passwords, optional git-history scrub.**

Credentials were hard-coded across ~30 helper scripts and committed to git, and `wp-config.php` is tracked. Hard-coded secrets in a git repo must be treated as **compromised** — the only true fix is to **rotate** them. Scrubbing the files (done below) prevents *future* leaks but does not remove secrets already in git history.

---

## Already done (by Claude, locally — no server access used)
- Added `env_loader.py` — a no-dependency loader that reads `.env` into the environment.
- **Scrubbed the FTP password** out of the Python scripts (`verify_phase4_staging.py`, `deploy_staging_phase4.py`, `list_ftp.py`, `install_plugins_staging.py`) — they now read `os.environ['FTP_PASSWORD']`. Syntax verified; 0 `.py` files still contain the literal.
- Moved the FTP password into `.env` (git-ignored) so the scripts keep working.
- Hardened `.gitignore` to stop tracking `wp-config.php`, `*.sql`, and the one-off PHP execution scripts.

> These are local working-tree edits, not committed. Review the diff and commit when ready. To undo any: `git checkout -- <file>`.

---

## Action required (you / the agent — needs server/cPanel)

### 1. Rotate the FTP password — ✅ DONE (2026-05-31)
Rotated in cPanel; new value is in `.env` as `FTP_PASSWORD`. The old literal (`bActive_FTP_9284!`) is now DEAD.
- **Heads-up:** the agent later wrote ~49 new helper scripts that re-hardcoded the OLD literal. All 49 were re-scrubbed to read `os.environ['FTP_PASSWORD']` via `env_loader` (syntax-verified; 0 remaining). The dead value persists only in git history (harmless now it's rotated; clear it via step 5 if the repo is ever shared).
- **Rule:** new scripts must read `os.environ['FTP_PASSWORD']` — never hardcode (guardrails rule #3).

### 2. Stop tracking secret files
```
git rm --cached wp-config.php recover.php wp_clone.php
git commit -m "chore(security): stop tracking secrets; scrub creds to .env"
```
(`.gitignore` already excludes them now; the files stay on disk.)

### 3. Delete the backdoor PHP scripts (local AND server)
These run code / create admins with no auth — they must not exist anywhere:
`recover.php`, `wp_clone.php`, `wp_setup.php`, `wp_ia_setup.php`, `wp_content_setup.php`, `wp_audit.php`, `wp_backup.php`, `wp_check_plugins.php`, `wp_install_plugins.php`, `step*.php`, `test_remote.php`, `test_wp_cli.php`, `test_wpcli.php`, `extract.php`, `install_plugins.php`, `verify_phase4.php`, `research.php`.
The agent reported removing them from the live server — **verify none remain in the web root**, and delete the local copies too.

### 4. Rotate the other previously-committed secrets (treat as compromised)
- **WordPress DB password** (was hard-coded `VeryStrongWPPass9284!`, lives in `wp-config.php`): change in cPanel → MySQL Databases, then update `wp-config.php` on the server.
- **Staging DB password** (`StagingDB_BActive2026!`): same.
- **cPanel password + any committed cPanel API token**: rotate in cPanel.
- **`bactive_support` admin password**: already rotated by the agent — good.

### 5. Git history still contains the old secrets
Once everything in 1 & 4 is rotated, the historical values are dead and harmless. If this repo will ever be pushed to a remote or shared, also scrub history with `git filter-repo` (or BFG) to remove `wp-config.php` and the old strings.

### 6. Never hard-code again
Secrets live only in `.env` (git-ignored). Python: `import os, env_loader` then `os.environ['KEY']`.
Keys: `FTP_HOST, FTP_USER, FTP_PASSWORD, CLOUDFLARE_API_TOKEN, CF_ZONE_ID, CPANEL_USER, CPANEL_API_TOKEN, WP_DB_PASSWORD, STAGING_DB_PASSWORD`.

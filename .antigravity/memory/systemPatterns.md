# System Patterns

## Architecture
- **CMS:** Standard WordPress deployment.
- **Hosting:** Namecheap shared hosting (`premium343`) with cPanel.
- **DNS / Edge:** Cloudflare (account ID `94a4dc...`) is used for DNS. To bypass the Cloudflare proxy for deployments, a dedicated unproxied record (`ftp.bactiveph.com`) is used for FTP access.
- **Database:** MySQL on shared hosting (`waypmvhk_bactwp`).

## Key Patterns
- **Deployment via FTP:** Python script (`ftp_move.py`) automates connecting via FTP and moving files from a `/wordpress` directory to the root directory `/*`, cleaning up artifacts (`latest.zip`) afterward.
- **Config Generation:** Python script (`generate_wp_config.py`) automates downloading fresh WordPress salts and injecting the database credentials into `wp-config.php`.

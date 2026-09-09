# Tech Context

## Stack
- **Core:** PHP / WordPress
- **Database:** MySQL (`waypmvhk_bactwp` / `waypmvhk_bactusr`)
- **Infrastructure / Hosting:** Namecheap Shared Hosting (cPanel), Cloudflare (DNS)
- **Scripting:** Python 3 (for automation scripts like `ftp_move.py`, `generate_wp_config.py`)

## Tooling & Verification
- **Environment Variables:** Managed through the ignored local `.env` file.

## Verification Commands
- `python3 generate_wp_config.py` (to generate fresh wp-config.php locally)
- `python3 ftp_move.py` (to execute post-upload file movements on FTP)

# Tech Context

## Stack
- **Core:** PHP / WordPress
- **Database:** MySQL (`waypmvhk_bactwp` / `waypmvhk_bactusr`)
- **Infrastructure / Hosting:** Namecheap Shared Hosting (cPanel), Cloudflare (DNS)
- **Scripting:** Python 3 (for automation scripts like `ftp_move.py`, `generate_wp_config.py`)

## Tooling & Verification
- **Source verification:** Inspect exact current source and ordinary project documentation, then run direct tests and verify the destination. Graphify is permanently retired; its old installation and provider settings are not active tooling or release gates.
- **Environment Variables:** Managed via `.env` file (contains cPanel credentials, Cloudflare tokens, FTP credentials, WordPress app password, and OpenRouter API key).

## Verification Commands
- `python3 generate_wp_config.py` (to generate fresh wp-config.php locally)
- `python3 ftp_move.py` (to execute post-upload file movements on FTP)

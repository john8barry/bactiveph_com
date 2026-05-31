# Tech Context

## Stack
- **Core:** PHP / WordPress
- **Database:** MySQL (`waypmvhk_bactwp` / `waypmvhk_bactusr`)
- **Infrastructure / Hosting:** Namecheap Shared Hosting (cPanel), Cloudflare (DNS)
- **Scripting:** Python 3 (for automation scripts like `ftp_move.py`, `generate_wp_config.py`)

## Tooling & Verification
- **Graphify:** Installed globally via `pipx` (Python 3.11). Configured to use OpenRouter API (`openrouter/owl-alpha` model) in `.graphify/providers.json`.
- **Environment Variables:** Managed via `.env` file (contains cPanel credentials, Cloudflare tokens, FTP credentials, WordPress app password, and OpenRouter API key).

## Verification Commands
- `python3 generate_wp_config.py` (to generate fresh wp-config.php locally)
- `python3 ftp_move.py` (to execute post-upload file movements on FTP)

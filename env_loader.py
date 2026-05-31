"""Minimal .env loader (no external dependencies).

Import this at the top of any script that needs credentials:

    import os, env_loader   # noqa: F401  (loads .env into os.environ on import)
    ftp_pass = os.environ["FTP_PASSWORD"]

Reads KEY=VALUE lines from the .env file that sits next to this module and
populates os.environ. Existing environment variables are NOT overwritten.
Secrets must live in .env (which is git-ignored) — never hard-coded in scripts.
"""
import os

_ENV_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env")


def load_env(path: str = _ENV_PATH) -> None:
    if not os.path.exists(path):
        return
    with open(path, "r", encoding="utf-8") as fh:
        for raw in fh:
            line = raw.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, value = line.partition("=")
            key = key.strip()
            value = value.strip().strip('"').strip("'")
            os.environ.setdefault(key, value)


load_env()

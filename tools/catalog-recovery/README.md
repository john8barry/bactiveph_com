# Local catalogue recovery preparation

This harness verifies a private off-server backup and prepares a **blocked local
rehearsal directory**. It does not execute Docker, import a database, extract site
archives, boot WordPress, read hosting credentials or contact providers. A passing
result means archive verification passed; it is not a successful restore drill.

Use Python 3.9 or newer. No third-party Python dependencies or local MySQL client
are required for this preparation. Docker is not invoked even if it is available.

## Read-only preview

Run from the isolated project checkout, supplying the exact private manifest:

```sh
python3 tools/catalog-recovery/recovery.py --backup-manifest '/absolute/private/production-backup/manifest.json'
```

Preview writes no files. It checks the production path claimed by the manifest,
all six content/database component kinds, private source permissions, sizes,
SHA-256 values, ZIP paths/types, every ZIP member CRC and gzip completion. It
rejects symlinks, path traversal, duplicate members/JSON keys, unrecognized
components, archive expansion beyond bounded limits and changing source files.
The result withholds SQL, configuration, filenames inside archives and credentials.
The manifest's remote `path` values are historical source claims, not local paths
or authenticated current production evidence.

## Explicit local preparation

After review, `--apply` creates one new private run below
`~/Library/Application Support/BactivePH/catalog-recovery/`:

```sh
python3 tools/catalog-recovery/recovery.py --backup-manifest '/absolute/private/production-backup/manifest.json' --run-id 'catalog-rehearsal-20260908' --apply
```

Only the new run is written. Original backups are opened read-only and never
renamed, changed, extracted or deleted. An existing run directory is rejected.
The program copies and rehashes each archive, emits a sanitized verification
report, writes `compose.json`, and generates new local-only WordPress credentials
and salts. Sensitive copies/configuration are mode 0600 inside mode 0700
directories. Failed preparation leaves an explicit incomplete marker in its
new directory; no pre-existing directory is cleaned up.

The generated Compose proposal has:

- Only a database service and a PHP/WordPress service, using inspected ARM64
  image digests with pulls disabled. Both entrypoints **unconditionally exit**.
- One internal IPv4-only network; no host network, Docker socket, extra network,
  production mount, published database port or raw-backup mount.
- One proposed web port bound only to `127.0.0.1` (default 8879); it is never
  opened by this program. Review the fixed `172.31.252.0/24` subnet for conflicts
  before any future runtime work.
- Explicit `dns: 127.0.0.1` and a read-only loopback-only `/etc/resolv.conf` mount.
  This avoids treating Docker's embedded DNS as an acceptable external resolver.
  The database's local name uses a fixed `/etc/hosts` mapping. Direct external
  DNS, HTTP, SMTP and other traffic still require a runtime negative test before
  boot is qualified; configuration alone is not proof of network containment.
- Nonroot users, all capabilities dropped, no privilege escalation, a read-only
  filesystem, bounded memory/processes and disabled container log collection.
- Draft PHP/MU defenses blocking outbound WordPress HTTP/mail, scheduling,
  asynchronous Action Scheduler, existing-user login and every web request.
  Noindex is not the containment control.

Raw backups are copied to `snapshot/` but mounted into neither container. No core
or product files are extracted. The generated `wp-config.php` uses a new local
password, new salts, local URL and a deliberately unrelated `recovery_` table
prefix. There is no configured database matching it. Do not remove the blocking
entrypoint or present this package as a runnable restored shop.

## Inspected backup and remaining coverage

The inspected September 8, 2026 02:58:37 UTC six-component backup contained
323,908,169 compressed bytes. Its independently rechecked sizes, hashes and
archive integrity passed. SQL included 137 tables, including WooCommerce HPOS,
order items and Action Scheduler, with 136 InnoDB tables and one Wordfence MEMORY
table. This is table presence, not cross-table consistency or successful import.

The SQL header recorded WordPress 7.1, PHP 8.2.33 and MariaDB 11.4.12. Archived
plugin headers recorded WooCommerce 11.1.0 and UpdraftPlus 1.26.7. The inspected
local MariaDB image is 11.4.13, so database patch parity remains unqualified.
PHP 8.2.33 is available locally; required extensions and the web-server behavior
remain separate checks.

Material limitations:

1. The package covers database and `wp-content` components. It does not establish
   coverage of site-root `wp-config.php`, `.htaccess`, `.user.ini`,
   `wordfence-waf.php`, WordPress core, host cron or web runtime configuration.
   Internal plugin/upload `.htaccess` files do not replace the root file.
2. The backup helper checks four required kinds, although this particular set
   has six. It delegates database export to UpdraftPlus and records no verified
   database-wide consistent snapshot. A CRC pass is not such a receipt.
3. ZIP/gzip artifacts are directly readable. Owner-only permissions are present;
   encrypted off-device storage, key custody and an independent physical copy
   are not established by this package. Never put these copies in Git.
4. **Preboot database sanitization is not implemented.** No full-database import
   or WordPress startup is permitted by the harness. An MU plugin alone cannot
   sanitize secrets or prove that direct socket/mail/plugin side effects are
   impossible.
5. This older package can inform a partial local drill. It does not substitute
   for the fresh complete backup required immediately before the live release.

## Next contained recovery implementation

The coordinator should review and implement these prerequisites before replacing
the unconditional blockers. This is a checklist, not an executable recipe:

1. Obtain the missing root/core/runtime recovery material through the production
   owner's serialized lane and establish a consistent database recovery point.
   Keep real configuration encrypted/private; generate local configuration
   instead of installing live secrets or WAF paths in the clone.
2. Qualify compatible pinned images, PHP extensions, local permissions and the
   database socket/network. Initialize a fresh disposable database with only
   generated local credentials; publish no database port. Import into the clone
   using its container-provided client, without starting WordPress. Inspect SQL
   privileges/routines/events and fail before unsupported statements; never give
   an imported application user server-wide FILE, SUPER or account-management
   permissions.
3. Implement and independently test the offline sanitizer: local site URLs;
   reviewed active plugin/theme/MU/drop-in allowlist; disabled gateway, API,
   webhook, marketing, shipping, catalogue-feed and other integration options;
   removed live secrets; cleared cron/Action Scheduler jobs; replaced user,
   application-password and session credentials; synthetic-only login; and
   appropriate customer-data minimization. Handle serialized values correctly.
   Do not guess secret-bearing option names or use broad text replacement in SQL.
4. Prove external DNS and direct-IP TCP/UDP requests fail from both containers,
   local database communication works, logs do not disclose data, and the host
   has no new nonloopback listener. Keep live services unavailable regardless of
   application hooks. A browser is a separate outbound surface: block third-party
   requests/service workers before browsing restored content or remote pixels.
5. Only then boot the reviewed clone, verify original catalogue and order-storage
   relationships, and run the synthetic order/stock/independent-price edit plus
   imagery rollback drill. Record current receipts, elapsed recovery time,
   interruption/conflict handling and preservation of newer commerce data.

## Offline checks

```sh
python3 -m unittest discover -s tests -p 'test_catalog_recovery.py'
```

These use synthetic archives in temporary private directories. They exercise
negative paths and preparation without a Docker daemon, network or WordPress.
The production writer hold remains with its existing owner.
